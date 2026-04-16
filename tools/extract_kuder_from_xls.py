#!/usr/bin/env python3
"""Extrae catálogo Kuder desde test.xls sin dependencias externas."""
from __future__ import annotations
import argparse, json, struct
from pathlib import Path
from collections import Counter, defaultdict

SCALE_CODE_MAP = {
    "0": "aire_libre",
    "1": "mecanico",
    "2": "calculo",
    "3": "cientifico",
    "4": "persuasivo",
    "5": "artistico",
    "6": "literario",
    "7": "musical",
    "8": "servicio_social",
    "9": "oficina",
    "V": "validez",
}


class CFB:
    def __init__(self, data: bytes):
        self.data = data
        h = data[:512]
        if h[:8] != b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1":
            raise ValueError("Archivo no es CFB/XLS legacy")
        self.sector_size = 1 << struct.unpack_from("<H", h, 30)[0]
        self.mini_sector_size = 1 << struct.unpack_from("<H", h, 32)[0]
        self.cutoff = struct.unpack_from("<I", h, 56)[0]
        first_dir = struct.unpack_from("<I", h, 48)[0]
        first_mini_fat = struct.unpack_from("<I", h, 60)[0]
        num_mini_fat = struct.unpack_from("<I", h, 64)[0]
        first_difat = struct.unpack_from("<I", h, 68)[0]
        num_difat = struct.unpack_from("<I", h, 72)[0]

        difat = [x for x in struct.unpack_from("<109I", h, 76) if x != 0xFFFFFFFF]
        sec = first_difat
        for _ in range(num_difat):
            if sec in (0xFFFFFFFE, 0xFFFFFFFF):
                break
            off = 512 + sec * self.sector_size
            arr = struct.unpack_from(f"<{(self.sector_size // 4) - 1}I", data, off)
            difat.extend([x for x in arr if x != 0xFFFFFFFF])
            sec = struct.unpack_from("<I", data, off + self.sector_size - 4)[0]

        self.fat: list[int] = []
        for s in difat:
            off = 512 + s * self.sector_size
            self.fat.extend(struct.unpack_from(f"<{self.sector_size // 4}I", data, off))

        dir_stream = self._read_chain(first_dir, None, self.fat, self.sector_size, 512)
        self.entries = []
        for i in range(0, len(dir_stream), 128):
            e = dir_stream[i : i + 128]
            if len(e) < 128:
                break
            nlen = struct.unpack_from("<H", e, 64)[0]
            name = e[: max(0, nlen - 2)].decode("utf-16le", "ignore")
            typ = e[66]
            start = struct.unpack_from("<I", e, 116)[0]
            size = struct.unpack_from("<Q", e, 120)[0]
            self.entries.append((name, typ, start, size))

        root = next(e for e in self.entries if e[0] == "Root Entry")
        self.mini_stream = self._read_chain(root[2], root[3], self.fat, self.sector_size, 512)
        self.mini_fat: list[int] = []
        sec = first_mini_fat
        for _ in range(num_mini_fat):
            if sec in (0xFFFFFFFE, 0xFFFFFFFF):
                break
            off = 512 + sec * self.sector_size
            self.mini_fat.extend(struct.unpack_from(f"<{self.sector_size // 4}I", data, off))
            sec = self.fat[sec]

    def _read_chain(self, start: int, size: int | None, table: list[int], chunk: int, base: int) -> bytes:
        out = bytearray()
        sec = start
        while sec not in (0xFFFFFFFE, 0xFFFFFFFF) and sec < len(table):
            off = base + sec * chunk
            out.extend(self.data[off : off + chunk])
            sec = table[sec]
            if size is not None and len(out) >= size:
                break
        return bytes(out[:size] if size is not None else out)

    def open(self, name: str) -> bytes:
        n, typ, start, size = next(e for e in self.entries if e[0] == name)
        if size < self.cutoff:
            out = bytearray()
            sec = start
            while sec not in (0xFFFFFFFE, 0xFFFFFFFF) and sec < len(self.mini_fat):
                off = sec * self.mini_sector_size
                out.extend(self.mini_stream[off : off + self.mini_sector_size])
                sec = self.mini_fat[sec]
                if len(out) >= size:
                    break
            return bytes(out[:size])
        return self._read_chain(start, size, self.fat, self.sector_size, 512)


def decode_rk(rk: int) -> float | int:
    mult100 = rk & 1
    is_int = rk & 2
    if is_int:
        val = struct.unpack("<i", struct.pack("<I", rk & 0xFFFFFFFC))[0] >> 2
        num = float(val)
    else:
        raw = (rk & 0xFFFFFFFC) << 32
        num = struct.unpack("<d", struct.pack("<Q", raw))[0]
    if mult100:
        num /= 100
    return int(num) if int(num) == num else num


class ChunkReader:
    def __init__(self, chunks: list[bytes]):
        self.chunks = chunks
        self.i = 0
        self.p = 0
    def read(self, n: int) -> bytes:
        out = bytearray()
        while n > 0:
            if self.i >= len(self.chunks):
                raise EOFError
            cur = self.chunks[self.i]
            rem = len(cur) - self.p
            if rem <= 0:
                self.i += 1
                self.p = 0
                continue
            t = min(rem, n)
            out.extend(cur[self.p : self.p + t])
            self.p += t
            n -= t
            if self.p >= len(cur):
                self.i += 1
                self.p = 0
        return bytes(out)
    def read_byte(self) -> int:
        return self.read(1)[0]
    def at_chunk_start(self) -> bool:
        return self.p == 0


def read_xl_unicode(reader: ChunkReader) -> str:
    cch = struct.unpack("<H", reader.read(2))[0]
    flags = reader.read_byte()
    is16 = flags & 0x01
    rich = flags & 0x08
    ext = flags & 0x04
    rt_runs = struct.unpack("<H", reader.read(2))[0] if rich else 0
    ext_size = struct.unpack("<I", reader.read(4))[0] if ext else 0

    chars = []
    remain = cch
    cur16 = 1 if is16 else 0
    while remain > 0:
        if reader.at_chunk_start():
            cur16 = reader.read_byte() & 0x01
        if cur16:
            chars.append(reader.read(2).decode("utf-16le", "ignore"))
        else:
            chars.append(reader.read(1).decode("latin1", "ignore"))
        remain -= 1
    if rt_runs:
        reader.read(4 * rt_runs)
    if ext_size:
        reader.read(ext_size)
    return "".join(chars)


def parse_workbook(path: Path):
    cfb = CFB(path.read_bytes())
    wb = cfb.open("Workbook")

    recs = []
    p = 0
    while p + 4 <= len(wb):
        rid, rlen = struct.unpack_from("<HH", wb, p)
        p += 4
        rec = wb[p : p + rlen]
        p += rlen
        recs.append((rid, rec))

    sheets = []
    sst = []
    i = 0
    while i < len(recs):
        rid, rec = recs[i]
        if rid == 0x0085:
            bof = struct.unpack_from("<I", rec, 0)[0]
            ln, flags = rec[6], rec[7]
            name = rec[8 : 8 + (2 * ln if flags & 1 else ln)].decode("utf-16le" if flags & 1 else "latin1", "ignore")
            sheets.append((name, bof))
        elif rid == 0x00FC:
            chunks = [rec]
            j = i + 1
            while j < len(recs) and recs[j][0] == 0x003C:
                chunks.append(recs[j][1])
                j += 1
            rd = ChunkReader(chunks)
            rd.read(8)
            while True:
                try:
                    sst.append(read_xl_unicode(rd))
                except Exception:
                    break
            i = j - 1
        elif rid == 0x000A and sheets:
            break
        i += 1

    rows_by_sheet = {}
    for sname, off in sheets:
        p = off
        rows = {}
        while p + 4 <= len(wb):
            rid, rlen = struct.unpack_from("<HH", wb, p)
            p += 4
            rec = wb[p : p + rlen]
            p += rlen
            if rid == 0x000A:
                break
            if rid in (0x00FD, 0x0203, 0x027E):
                r, c = struct.unpack_from("<HH", rec, 0)
                if rid == 0x00FD:
                    idx = struct.unpack_from("<I", rec, 6)[0]
                    v = sst[idx] if idx < len(sst) else f"SST#{idx}"
                elif rid == 0x0203:
                    v = struct.unpack_from("<d", rec, 6)[0]
                else:
                    v = decode_rk(struct.unpack_from("<I", rec, 6)[0])
                rows.setdefault(r, {})[c] = v
        rows_by_sheet[sname] = rows
    return rows_by_sheet


def write_json(path: Path, payload):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def to_excel_col_name(col_idx_zero_based: int) -> str:
    value = col_idx_zero_based + 1
    result = ""
    while value > 0:
        value, remainder = divmod(value - 1, 26)
        result = chr(ord("A") + remainder) + result
    return result


def is_number(value) -> bool:
    return isinstance(value, (int, float)) and not isinstance(value, bool)


def extract_percentiles(rows_by_sheet: dict) -> tuple[dict, dict, dict]:
    perfil = rows_by_sheet.get("ESTTENES Y PERFIL", {})
    if not perfil:
        raise ValueError("No se encontró la hoja 'ESTTENES Y PERFIL' para extraer percentiles.")

    # Cabeceras observadas en test.xls:
    # - Códigos de escala (male): D,G,J,...,AE
    # - Percentiles (male):      E,H,K,...,AF
    # - Códigos de escala (female): AI,AL,AO,...,BJ
    # - Percentiles (female):       AJ,AM,AP,...,BK
    header_row = perfil.get(0, {})
    scale_codes_male = [str(header_row.get(3 + (3 * i), "")).strip() for i in range(10)]
    scale_codes_female = [str(header_row.get(34 + (3 * i), "")).strip() for i in range(10)]

    scale_ids_male = [SCALE_CODE_MAP.get(code, "") for code in scale_codes_male]
    scale_ids_female = [SCALE_CODE_MAP.get(code, "") for code in scale_codes_female]
    if any(not sid for sid in scale_ids_male + scale_ids_female):
        raise ValueError("No se pudieron resolver todos los códigos de escala de percentiles.")

    start_row = None
    for r in sorted(perfil.keys()):
        row = perfil.get(r, {})
        if str(row.get(0, "")).strip().lower() == "muj" and row.get(1) == 0:
            start_row = r
            break
    if start_row is None:
        raise ValueError("No se encontró la fila de inicio esperada ('muj' con puntaje 0).")

    male = {scale_id: [] for scale_id in scale_ids_male}
    female = {scale_id: [] for scale_id in scale_ids_female}

    max_row = max(perfil.keys())
    for r in range(start_row, max_row + 1):
        row = perfil.get(r, {})
        raw = r - start_row
        row_has_any = False

        for i, scale_id in enumerate(scale_ids_male):
            percentile_col = 4 + (3 * i)
            value = row.get(percentile_col)
            if is_number(value):
                male[scale_id].append({"bruto": raw, "percentil": int(value)})
                row_has_any = True

        for i, scale_id in enumerate(scale_ids_female):
            percentile_col = 35 + (3 * i)
            value = row.get(percentile_col)
            if is_number(value):
                female[scale_id].append({"bruto": raw, "percentil": int(value)})
                row_has_any = True

        # Corta al detectar tramo completamente vacío después del inicio.
        if not row_has_any and r > start_row + 5:
            break

    extraction_report = {
        "sheet": "ESTTENES Y PERFIL",
        "base_row_excel_1_indexed": start_row + 1,
        "raw_formula": "bruto = fila_actual - fila_base",
        "male_scales": {k: len(v) for k, v in male.items()},
        "female_scales": {k: len(v) for k, v in female.items()},
    }

    male_payload = {
        "sexo": "M",
        "fuente": "test.xls::ESTTENES Y PERFIL",
        "lookup_method": "floor",
        "percentiles": male,
    }
    female_payload = {
        "sexo": "F",
        "fuente": "test.xls::ESTTENES Y PERFIL",
        "lookup_method": "floor",
        "percentiles": female,
    }
    return male_payload, female_payload, extraction_report


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--xls", default="test.xls")
    ap.add_argument("--config-dir", default="config/test-vocacional")
    ap.add_argument("--report", default="storage/logs/extraction_report.json")
    args = ap.parse_args()

    rows_by_sheet = parse_workbook(Path(args.xls))
    prueba = rows_by_sheet["PRUEBA"]

    scale_letter_cols = [(7 + 3 * i, code) for i, code in enumerate(["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "V"])]

    activities = []
    discarded = []
    scoring_matrix = {}
    for r in range(3, max(prueba.keys()) + 1):
        row = prueba.get(r, {})
        texto = str(row.get(1, "")).strip()
        literal = str(row.get(3, "")).strip()
        is_activity_row = bool(texto) and len(literal) == 1 and literal.isalpha()
        if (not is_activity_row) or texto.startswith("TE GUSTA"):
            discarded.append({"row": r + 1, "reason": "sin_texto_actividad_o_encabezado"})
            continue
        mas_codes = [code for col, code in scale_letter_cols if row.get(col - 1) == 1 and code in SCALE_CODE_MAP]
        menos_codes = [code for col, code in scale_letter_cols if row.get(col + 1) == 1 and code in SCALE_CODE_MAP]
        mas = {SCALE_CODE_MAP[c]: 1 for c in mas_codes}
        menos = {SCALE_CODE_MAP[c]: 1 for c in menos_codes}

        activities.append({"row": r + 1, "texto": texto})
        scoring_matrix[r + 1] = {"mas": mas, "menos": menos}

    blocks = []
    for i in range(0, len(activities), 3):
        chunk = activities[i : i + 3]
        if len(chunk) < 3:
            break
        block_id = f"B{(i // 3) + 1:03d}"
        block_acts = []
        for j, a in enumerate(chunk):
            aid = f"A{i + j + 1:04d}"
            block_acts.append({"id": aid, "texto": a["texto"], "bloque": block_id, "indice_en_bloque": j + 1})
        blocks.append({"id": block_id, "orden": (i // 3) + 1, "actividades": block_acts})

    config_dir = Path(args.config_dir)
    write_json(config_dir / "questions_blocks.json", {"blocks": blocks})

    matrix_by_block = {}
    for block in blocks:
        block_id = block["id"]
        position_map = {}
        for activity in block["actividades"]:
            activity_idx = int(activity["indice_en_bloque"])
            activity_row = activities[int(activity["id"][1:]) - 1]["row"]
            matrix_row = scoring_matrix.get(activity_row, {"mas": {}, "menos": {}})
            position_map[str(activity_idx)] = {
                "fila_excel": activity_row,
                "mas": {"scales": matrix_row["mas"], "rule": "sumar_peso_directo"},
                "menos": {"scales": matrix_row["menos"], "rule": "sumar_peso_directo"},
            }
        matrix_by_block[block_id] = position_map

    scale_columns = []
    for col, code in scale_letter_cols:
        scale_id = SCALE_CODE_MAP[code]
        scale_columns.append({
            "escala_id": scale_id,
            "codigo_excel": code,
            "columna_codigo": to_excel_col_name(col),
            "columna_mas": to_excel_col_name(col - 1),
            "columna_menos": to_excel_col_name(col + 1),
            "peso_marcador": 1,
        })

    write_json(config_dir / "scoring_rules.json", {
        "scoring_rules": {
            "modelo": "por_bloque_indice_respuesta",
            "fuente": "Hoja PRUEBA de test.xls (matriz de columnas +/- por escala).",
            "respuesta_por_bloque": {"mas": {"requerido": 1}, "menos": {"requerido": 1}},
            "formula": "puntaje_bruto_escala = sum(peso_marcador de la matriz para la respuesta seleccionada)",
            "escalas_columnas_excel": scale_columns,
            "matriz_por_bloque": matrix_by_block,
        }
    })

    write_json(config_dir / "validity_rules.json", {
        "validity_rules": {
            "requerimientos_base": {"mas_por_bloque": 1, "menos_por_bloque": 1, "permitir_duplicado_en_bloque": False},
            "metricas": [
                {"id": "omisiones", "formula": "count(bloque where mas is null or menos is null)", "umbral_invalido": 1},
                {"id": "colision_mas_menos", "formula": "count(bloque where actividad_mas == actividad_menos)", "umbral_invalido": 1},
                {"id": "indice_validez", "formula": "score('validez')"},
            ],
            "nota": "Reglas de validez psicométrica deben revisarse contra manual; este archivo conserva estructura operativa.",
        }
    })

    male_percentiles, female_percentiles, percentile_report = extract_percentiles(rows_by_sheet)
    write_json(config_dir / "percentiles" / "male.json", male_percentiles)
    write_json(config_dir / "percentiles" / "female.json", female_percentiles)

    excel_mapping = {
        "excel_mapping": {
            "sheet_prueba": "PRUEBA",
            "columnas": {
                "texto_actividad": "B (índice 2 en Excel / col=1 base 0)",
                "matriz_codigos": "H,K,N,Q,T,W,Z,AC,AF,AI,AL (0..9,V)",
                "marcador_mas": "columna izquierda inmediata de cada código",
                "marcador_menos": "columna derecha inmediata de cada código",
            },
            "codigo_escala": SCALE_CODE_MAP,
        }
    }
    write_json(config_dir / "excel_mapping.json", excel_mapping)

    reporte = {
        "archivo_origen": args.xls,
        "total_actividades_detectadas": len(activities),
        "total_bloques_generados": len(blocks),
        "actividades_clave_completa": len(activities),
        "actividades_clave_incompleta": 0,
        "escalas_detectadas": sorted(set(SCALE_CODE_MAP.values())),
        "mapeo_excel_escalas": SCALE_CODE_MAP,
        "filas_descartadas": discarded,
        "filas_descartadas_total": len(discarded),
        "muestras_actividades": activities[:5],
        "actividades_incompletas_muestra": [],
        "nota": "La matriz de scoring se exporta por bloque/posición/respuesta usando PRUEBA como única fuente de verdad.",
        "percentiles_extraccion": percentile_report,
    }
    write_json(Path(args.report), reporte)
    print(json.dumps({"ok": True, "activities": len(activities), "blocks": len(blocks), "modelo": "por_bloque_indice_respuesta"}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

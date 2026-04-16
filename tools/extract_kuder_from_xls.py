#!/usr/bin/env python3
"""Extractor reproducible para regenerar configuración de Kuder desde test.xls.

Uso:
  python tools/extract_kuder_from_xls.py --xls test.xls
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from collections import Counter, defaultdict
from dataclasses import dataclass
from pathlib import Path
from typing import Any

try:
    import xlrd  # type: ignore
except ModuleNotFoundError as exc:  # pragma: no cover
    raise SystemExit(
        "Dependencia faltante: xlrd. Instala en tu entorno con `pip install xlrd==2.0.1`."
    ) from exc


SCALES_MAIN = [
    "aire_libre",
    "mecanico",
    "calculo",
    "cientifico",
    "persuasivo",
    "artistico",
    "literario",
    "musical",
    "servicio_social",
    "oficina",
]
SCALES_ALL = [*SCALES_MAIN, "validez"]


@dataclass
class Activity:
    id: str
    block_id: str
    text: str
    row_index: int
    mas: dict[str, int]
    menos: dict[str, int]


class Ambiguity(Exception):
    pass


HEADER_EXCLUSION_PATTERNS = [
    "prueba",
    "instrucciones",
    "marca",
    "mas",
    "menos",
    "nombre",
    "fecha",
    "edad",
    "sexo",
    "ocupacion",
    "pagina",
]


def normalize_header(value: Any) -> str:
    text = str(value or "").strip().lower()
    text = re.sub(r"\s+", " ", text)
    repl = {
        "á": "a",
        "é": "e",
        "í": "i",
        "ó": "o",
        "ú": "u",
        "ñ": "n",
        "+": " mas ",
        "-": " menos ",
        "(": " ",
        ")": " ",
        "/": " ",
    }
    for src, dst in repl.items():
        text = text.replace(src, dst)
    return re.sub(r"\s+", " ", text).strip()


def cell_text(sheet: "xlrd.sheet.Sheet", row: int, col: int) -> str:
    value = sheet.cell_value(row, col)
    if isinstance(value, float) and value.is_integer():
        return str(int(value))
    return str(value).strip()


def detect_prueba_sheet(book: "xlrd.book.Book") -> "xlrd.sheet.Sheet":
    for name in book.sheet_names():
        if normalize_header(name) == "prueba":
            return book.sheet_by_name(name)
    raise Ambiguity("No se encontró hoja 'PRUEBA'.")


def clean_activity_text(value: str) -> str:
    text = str(value or "").replace("\n", " ").strip()
    text = re.sub(r"\s+", " ", text)
    text = re.sub(r"^\s*[\(\[]?\d{1,3}[\)\].:-]?\s*", "", text)
    return text.strip(" .;:-")


def looks_like_activity_text(text: str) -> tuple[bool, str]:
    normalized = normalize_header(text)
    if not normalized:
        return False, "empty"
    if normalized.isdigit():
        return False, "numeric_only"
    if len(normalized) < 8:
        return False, "too_short"
    if not re.search(r"[a-záéíóúñ]", text, flags=re.IGNORECASE):
        return False, "no_letters"
    if any(pattern in normalized for pattern in HEADER_EXCLUSION_PATTERNS):
        return False, "header_or_instruction"
    return True, ""


def detect_activity_start_row(rows_candidates: list[list[tuple[int, str]]]) -> int:
    for idx in range(len(rows_candidates)):
        window = rows_candidates[idx : idx + 12]
        total = sum(len(row) for row in window)
        rich_rows = sum(1 for row in window if len(row) >= 2)
        if total >= 18 and rich_rows >= 6:
            return idx
    return 0


def parse_prueba(
    sheet: "xlrd.sheet.Sheet", ambiguities: list[str], debug: bool = False
) -> tuple[list[Activity], dict[str, Any], list[dict[str, Any]]]:
    rows_candidates: list[list[tuple[int, str]]] = []
    debug_rows: list[dict[str, Any]] = []

    for r in range(sheet.nrows):
        raw_values = [cell_text(sheet, r, c) for c in range(sheet.ncols)]
        row_candidates: list[tuple[int, str]] = []
        row_discard_reasons: list[str] = []
        dedupe_seen: set[str] = set()

        for c, raw in enumerate(raw_values):
            cleaned = clean_activity_text(raw)
            accepted, reason = looks_like_activity_text(cleaned)
            if accepted:
                dedupe_key = normalize_header(cleaned)
                if dedupe_key in dedupe_seen:
                    row_discard_reasons.append(f"col={c+1}: duplicated_text")
                    continue
                dedupe_seen.add(dedupe_key)
                row_candidates.append((c, cleaned))
            elif cleaned:
                row_discard_reasons.append(f"col={c+1}: {reason}")

        row_candidates.sort(key=lambda item: item[0])
        rows_candidates.append(row_candidates)
        debug_rows.append(
            {
                "row_index": r + 1,
                "raw_values": raw_values,
                "detected_text": [text for _, text in row_candidates],
                "accepted_as_activity": len(row_candidates) > 0,
                "discard_reason": "; ".join(row_discard_reasons) if row_discard_reasons else "",
            }
        )

    start_row = detect_activity_start_row(rows_candidates)
    candidate_cells: list[tuple[int, int, str]] = []
    for r in range(start_row, len(rows_candidates)):
        for c, text in rows_candidates[r]:
            candidate_cells.append((r, c, text))

    activities: list[Activity] = []
    for idx, (r, c, text) in enumerate(candidate_cells, start=1):
        block_num = ((idx - 1) // 3) + 1
        activity_id = f"A{idx:04d}"
        block_id = f"B{block_num:03d}"

        mas: dict[str, int] = {}
        menos: dict[str, int] = {}

        # Intento de extracción de claves en columnas próximas a la derecha.
        # Si no se logra identificar señal explícita, reportamos ambigüedad.
        nearby = [normalize_header(cell_text(sheet, r, x)) for x in range(c + 1, min(c + 12, sheet.ncols))]
        blob = " | ".join([x for x in nearby if x])
        for scale in SCALES_ALL:
            token = scale.replace("_", " ")
            if f"{token} mas" in blob or f"mas {token}" in blob:
                mas[scale] = 1
            if f"{token} menos" in blob or f"menos {token}" in blob:
                menos[scale] = 1

        if not mas and not menos:
            ambiguities.append(
                f"Actividad {activity_id} (fila={r+1}, col={c+1}) sin claves mas/menos detectables por heurística."
            )

        activities.append(
            Activity(
                id=activity_id,
                block_id=block_id,
                text=text,
                row_index=r,
                mas=mas,
                menos=menos,
            )
        )

    discarded_rows: list[dict[str, Any]] = []
    for row in debug_rows:
        if not row["accepted_as_activity"] and row["discard_reason"]:
            discarded_rows.append(
                {
                    "row_index": row["row_index"],
                    "raw_values": row["raw_values"],
                    "discard_reason": row["discard_reason"],
                }
            )
        if len(discarded_rows) >= 10:
            break

    diagnostics = {
        "sheet_name": sheet.name,
        "total_rows_read": sheet.nrows,
        "total_cols_read": sheet.ncols,
        "activity_detection_start_row": start_row + 1,
        "detected_activities": len(activities),
        "first_15_activities": [a.text for a in activities[:15]],
        "discarded_rows_examples": discarded_rows,
    }

    if len(activities) != 504:
        ambiguities.append(
            "Conteo de actividades distinto a 504 en PRUEBA. "
            f"Detectadas={len(activities)}; filas_leidas={sheet.nrows}; inicio_detectado_fila={start_row + 1}."
        )

    if debug:
        print(f"[DEBUG] Hoja usada: {sheet.name}")
        print(f"[DEBUG] Columnas totales: {sheet.ncols}")
        print("[DEBUG] Índices de columnas relevantes (con texto detectado):", sorted({c + 1 for _, c, _ in candidate_cells}))
        print(f"[DEBUG] Filas detectadas como actividad (desde fila {start_row + 1}):")
        print([r + 1 for r, _, _ in candidate_cells[:120]])
        omitted = [row["row_index"] for row in debug_rows if not row["accepted_as_activity"]]
        print("[DEBUG] Filas omitidas:", omitted[:120])

    return activities, diagnostics, debug_rows


def build_questions_blocks(activities: list[Activity]) -> dict[str, Any]:
    blocks: list[dict[str, Any]] = []
    total_blocks = len(activities) // 3
    for block_num in range(1, total_blocks + 1):
        block_id = f"B{block_num:03d}"
        chunk = activities[(block_num - 1) * 3 : block_num * 3]
        blocks.append(
            {
                "id": block_id,
                "orden": block_num,
                "actividades": [
                    {
                        "id": a.id,
                        "texto": a.text,
                        "bloque": a.block_id,
                        "claves": {"mas": a.mas, "menos": a.menos},
                    }
                    for a in chunk
                ],
            }
        )
    return {"blocks": blocks}


def build_scoring_rules() -> dict[str, Any]:
    return {
        "scoring_rules": {
            "respuesta_por_bloque": {
                "mas": {"requerido": 1, "multiplicador": 1},
                "menos": {"requerido": 1, "multiplicador": -1},
            },
            "formula": "puntaje_bruto_escala = suma(claves_mas * 1) + suma(claves_menos * -1)",
            "normalizacion": {"minimo": -100, "maximo": 100, "redondeo": 0},
            "overrides": [
                {
                    "descripcion": "validez solo suma en 'mas'.",
                    "si_escala": "validez",
                    "aplicar": {"mas": 1, "menos": 0},
                }
            ],
        }
    }


def build_validity_rules() -> dict[str, Any]:
    return {
        "validity_rules": {
            "requerimientos_base": {
                "mas_por_bloque": 1,
                "menos_por_bloque": 1,
                "permitir_duplicado_en_bloque": False,
            },
            "metricas": [
                {
                    "id": "omisiones",
                    "descripcion": "Cantidad de bloques sin respuesta completa.",
                    "formula": "count(bloque where mas is null or menos is null)",
                    "umbral_invalido": 1,
                },
                {
                    "id": "colision_mas_menos",
                    "descripcion": "Misma actividad marcada como mas y menos dentro del mismo bloque.",
                    "formula": "count(bloque where actividad_mas == actividad_menos)",
                    "umbral_invalido": 1,
                },
                {
                    "id": "indice_validez",
                    "descripcion": "Puntaje bruto de la escala de validez.",
                    "formula": "score('validez')",
                    "rango_valido": {"min": -3, "max": 3},
                },
            ],
            "decision": [
                {"si": "omisiones >= 1 or colision_mas_menos >= 1", "estado": "invalido"},
                {"si": "indice_validez < -3 or indice_validez > 3", "estado": "dudoso"},
                {"si": "default", "estado": "valido"},
            ],
        }
    }


def parse_percentiles(book: "xlrd.book.Book", ambiguities: list[str]) -> tuple[dict[str, Any], dict[str, Any]]:
    # Heurística: buscar hoja que contenga encabezados de ambos sexos.
    sheet = None
    for name in book.sheet_names():
        n = normalize_header(name)
        if "esttenes" in n or "perfil" in n:
            sheet = book.sheet_by_name(name)
            break

    if sheet is None:
        ambiguities.append("No se encontró hoja normativa (ESTTENES/PERFIL); percentiles vacíos.")
        return ({"sexo": "M", "percentiles": {}}, {"sexo": "F", "percentiles": {}})

    male: dict[str, list[dict[str, int]]] = defaultdict(list)
    female: dict[str, list[dict[str, int]]] = defaultdict(list)

    # Extracción tentativa: filas con patrón [escala, bruto, pM, pF].
    for r in range(sheet.nrows):
        row = [cell_text(sheet, r, c) for c in range(min(sheet.ncols, 12))]
        if not any(row):
            continue
        key = normalize_header(row[0])
        if key in SCALES_ALL and row[1] and row[2] and row[3]:
            try:
                bruto = int(float(row[1]))
                p_m = int(float(row[2]))
                p_f = int(float(row[3]))
            except ValueError:
                continue
            male[key].append({"bruto": bruto, "percentil": p_m})
            female[key].append({"bruto": bruto, "percentil": p_f})

    if not male:
        ambiguities.append(
            "No se detectaron percentiles con heurística [escala, bruto, pM, pF] en hoja normativa."
        )

    return ({"sexo": "M", "percentiles": male}, {"sexo": "F", "percentiles": female})


def build_scales() -> dict[str, Any]:
    labels = {
        "aire_libre": "Interés por trabajo al aire libre",
        "mecanico": "Interés mecánico",
        "calculo": "Interés por cálculo",
        "cientifico": "Interés científico",
        "persuasivo": "Interés persuasivo",
        "artistico": "Interés artístico",
        "literario": "Interés literario",
        "musical": "Interés musical",
        "servicio_social": "Interés por servicio social",
        "oficina": "Interés de oficina",
        "validez": "Índice de validez",
    }
    return {
        "scales": [
            {"id": sid, "nombre": labels[sid], "grupo": "control" if sid == "validez" else "intereses"}
            for sid in SCALES_ALL
        ]
    }


def build_excel_mapping(book: "xlrd.book.Book") -> dict[str, Any]:
    return {
        "excel_mapping": {
            "workbook": Path(book.filename).name if getattr(book, "filename", None) else "test.xls",
            "sheets": book.sheet_names(),
            "notes": "Mapeo detectado automáticamente por extractor; revisar si cambia estructura del XLS.",
        }
    }


def rules_count(activities: list[Activity], side: str) -> dict[str, int]:
    counter: Counter[str] = Counter()
    for activity in activities:
        for scale in (activity.mas if side == "mas" else activity.menos):
            counter[scale] += 1
    return dict(counter)


def write_json(path: Path, payload: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--xls", default="test.xls")
    parser.add_argument("--config-dir", default="config/test-vocacional")
    parser.add_argument("--report", default="storage/logs/extraction_report.json")
    parser.add_argument("--debug", action="store_true")
    args = parser.parse_args()

    xls_path = Path(args.xls)
    if not xls_path.exists():
        raise SystemExit(f"No existe el archivo XLS: {xls_path}")

    ambiguities: list[str] = []
    diagnostics: dict[str, Any] = {}
    debug_rows: list[dict[str, Any]] = []
    try:
        book = xlrd.open_workbook(str(xls_path), formatting_info=False)
        prueba = detect_prueba_sheet(book)
        activities, diagnostics, debug_rows = parse_prueba(prueba, ambiguities, debug=args.debug)
        male, female = parse_percentiles(book, ambiguities)
    except Ambiguity as exc:
        ambiguities.append(str(exc))
        activities = []
        diagnostics = {
            "sheet_name": "PRUEBA",
            "total_rows_read": 0,
            "total_cols_read": 0,
            "activity_detection_start_row": None,
            "detected_activities": 0,
            "first_15_activities": [],
            "discarded_rows_examples": [],
        }
        male, female = {"sexo": "M", "percentiles": {}}, {"sexo": "F", "percentiles": {}}
        book = xlrd.open_workbook(str(xls_path), formatting_info=False)

    config_dir = Path(args.config_dir)
    complete_activities = activities[: (len(activities) // 3) * 3]
    write_json(config_dir / "questions_blocks.json", build_questions_blocks(complete_activities))
    write_json(config_dir / "scoring_rules.json", build_scoring_rules())
    write_json(config_dir / "validity_rules.json", build_validity_rules())
    write_json(config_dir / "percentiles" / "male.json", male)
    write_json(config_dir / "percentiles" / "female.json", female)
    write_json(config_dir / "scales.json", build_scales())
    write_json(config_dir / "excel_mapping.json", build_excel_mapping(book))

    report = {
        "archivo_origen": str(xls_path),
        "total_hojas_leidas": len(book.sheet_names()),
        "total_actividades": len(complete_activities),
        "total_bloques": len(complete_activities) // 3 if complete_activities else 0,
        "diagnostico_prueba": diagnostics,
        "escalas_detectadas": sorted(
            set().union(*(a.mas.keys() for a in complete_activities), *(a.menos.keys() for a in complete_activities))
        ),
        "reglas_por_escala": {
            "mas": rules_count(complete_activities, "mas"),
            "menos": rules_count(complete_activities, "menos"),
        },
        "ambiguedades": ambiguities,
    }
    write_json(Path(args.report), report)
    write_json(Path("storage/logs/debug_prueba_rows.json"), {"rows": debug_rows})

    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

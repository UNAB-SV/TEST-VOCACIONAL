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


TRUE_HEADER_PATTERNS = [
    re.compile(r"^prueba$"),
    re.compile(r"^instrucciones?$"),
    re.compile(r"^marca\b"),
    re.compile(r"^nombre\b"),
    re.compile(r"^fecha\b"),
    re.compile(r"^edad\b"),
    re.compile(r"^sexo\b"),
    re.compile(r"^ocupacion\b"),
    re.compile(r"^pagina\b"),
    re.compile(r"^suma de puta?j?es por escala$"),
    re.compile(r"^suma de puntajes por escala$"),
    re.compile(r"^totales?$"),
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


def is_true_header_or_auxiliary(text: str) -> bool:
    normalized = normalize_header(text)
    if not normalized:
        return False
    return any(pattern.search(normalized) for pattern in TRUE_HEADER_PATTERNS)


def looks_like_activity_text(text: str) -> tuple[bool, str]:
    normalized = normalize_header(text)
    if not normalized:
        return False, "empty"
    if normalized.isdigit():
        return False, "numeric_only"
    if not re.search(r"[a-záéíóúñ]", text, flags=re.IGNORECASE):
        return False, "no_letters"
    if is_true_header_or_auxiliary(normalized):
        return False, "header_or_instruction"
    words = [w for w in re.split(r"\s+", normalized) if w]
    if len(words) < 3:
        return False, "too_short_phrase"
    # Detecta frases de actividad (verbos en infinitivo y narrativas típicas).
    has_activity_verb = any(
        re.search(r"(ar|er|ir|arse|erse|irse|ando|iendo)$", w) for w in words
    )
    if not has_activity_verb and len(words) < 5:
        return False, "not_activity_like"
    return True, ""


def detect_activity_start_row(accepted_rows: list[dict[str, Any]]) -> int:
    for idx in range(len(accepted_rows)):
        window = accepted_rows[idx : idx + 18]
        valid = sum(1 for row in window if row.get("accepted_as_activity"))
        if valid >= 8:
            return idx
    return 0


def parse_prueba(
    sheet: "xlrd.sheet.Sheet", ambiguities: list[str], debug: bool = False
) -> tuple[list[Activity], dict[str, Any], list[dict[str, Any]]]:
    debug_rows: list[dict[str, Any]] = []
    column_phrase_counter: Counter[int] = Counter()
    pre_rows: list[dict[str, Any]] = []

    for r in range(sheet.nrows):
        raw_values = [cell_text(sheet, r, c) for c in range(sheet.ncols)]
        candidate_cells: list[tuple[int, str]] = []
        row_discard_reasons: list[str] = []

        for c, raw in enumerate(raw_values):
            cleaned = clean_activity_text(raw)
            accepted, reason = looks_like_activity_text(cleaned)
            if accepted:
                candidate_cells.append((c, cleaned))
                column_phrase_counter[c] += 1
            elif cleaned:
                row_discard_reasons.append(f"col={c+1}: {reason}")
        pre_rows.append(
            {
                "row_index": r + 1,
                "raw_values": raw_values,
                "candidate_cells": candidate_cells,
                "discard_reasons": row_discard_reasons,
            }
        )

    # Determina columna principal de actividades basada en densidad de frases válidas.
    primary_activity_col = column_phrase_counter.most_common(1)[0][0] if column_phrase_counter else 1

    accepted_rows: list[dict[str, Any]] = []
    for row in pre_rows:
        row_candidates = sorted(row["candidate_cells"], key=lambda item: item[0])
        selected: tuple[int, str] | None = None
        reason = ""
        for c, text in row_candidates:
            if c == primary_activity_col:
                selected = (c, text)
                reason = f"accepted_primary_col={c+1}"
                break
        if selected is None and row_candidates:
            selected = row_candidates[0]
            reason = f"accepted_fallback_col={selected[0]+1}"
        if selected is None:
            reason = "; ".join(row["discard_reasons"]) if row["discard_reasons"] else "no_candidate_text"

        accepted_rows.append(
            {
                "row_index": row["row_index"],
                "raw_values": row["raw_values"],
                "detected_text": [text for _, text in row_candidates],
                "accepted_as_activity": selected is not None,
                "selected_col": selected[0] + 1 if selected else None,
                "selected_text": selected[1] if selected else "",
                "reason": reason,
            }
        )
        debug_rows.append(accepted_rows[-1])

    start_row = detect_activity_start_row(accepted_rows)
    candidate_cells: list[tuple[int, int, str]] = []
    for row in accepted_rows[start_row:]:
        if row["accepted_as_activity"]:
            candidate_cells.append((row["row_index"] - 1, (row["selected_col"] - 1), row["selected_text"]))

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

    accepted_rows_list = [
        {
            "row_index": row["row_index"],
            "selected_col": row["selected_col"],
            "text": row["selected_text"],
            "reason": row["reason"],
        }
        for row in accepted_rows
        if row["accepted_as_activity"]
    ]
    discarded_rows = [
        {
            "row_index": row["row_index"],
            "raw_values": row["raw_values"],
            "reason": row["reason"],
        }
        for row in accepted_rows
        if not row["accepted_as_activity"]
    ]

    diagnostics = {
        "sheet_name": sheet.name,
        "total_rows_read": sheet.nrows,
        "total_cols_read": sheet.ncols,
        "primary_activity_col": primary_activity_col + 1,
        "activity_detection_start_row": start_row + 1,
        "detected_activities": len(activities),
        "first_30_activities": [a.text for a in activities[:30]],
        "accepted_rows": accepted_rows_list,
        "discarded_rows": discarded_rows,
    }

    if len(activities) != 504:
        ambiguities.append(
            "Conteo de actividades distinto a 504 en PRUEBA. "
            f"Detectadas={len(activities)}; filas_leidas={sheet.nrows}; inicio_detectado_fila={start_row + 1}."
        )

    if debug:
        print(f"[DEBUG] Hoja usada: {sheet.name}")
        print(f"[DEBUG] Columnas totales: {sheet.ncols}")
        print(f"[DEBUG] Columna principal detectada: {primary_activity_col + 1}")
        if activities:
            print(f"[DEBUG] Primera fila válida real: {activities[0].row_index + 1}")
            print(f"[DEBUG] Última fila válida real: {activities[-1].row_index + 1}")
        print(f"[DEBUG] Cantidad total de actividades aceptadas: {len(activities)}")
        print("[DEBUG] Primeras 30 actividades aceptadas:")
        for idx, activity in enumerate(activities[:30], start=1):
            print(f"  {idx:02d}. {activity.text}")

    return activities, diagnostics, debug_rows


def build_questions_blocks(activities: list[Activity]) -> dict[str, Any]:
    if not activities:
        raise ValueError("No hay actividades para generar bloques.")
    remainder = len(activities) % 3
    if remainder != 0:
        raise ValueError(
            "Total de actividades no divisible entre 3. "
            f"Detectadas={len(activities)}; sobrantes={remainder}."
        )
    blocks: list[dict[str, Any]] = []
    total_blocks = len(activities) // 3
    for block_num in range(1, total_blocks + 1):
        block_id = f"B{block_num:03d}"
        chunk = activities[(block_num - 1) * 3 : block_num * 3]
        if len(chunk) != 3:
            raise ValueError(
                f"Bloque incompleto detectado: {block_id} con {len(chunk)} actividades."
            )
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
            "first_30_activities": [],
            "accepted_rows": [],
            "discarded_rows": [],
        }
        male, female = {"sexo": "M", "percentiles": {}}, {"sexo": "F", "percentiles": {}}
        book = xlrd.open_workbook(str(xls_path), formatting_info=False)

    config_dir = Path(args.config_dir)
    if len(activities) % 3 != 0:
        ambiguities.append(
            "Error de extracción: el total de actividades no es múltiplo de 3. "
            f"Total={len(activities)}."
        )
        report = {
            "archivo_origen": str(xls_path),
            "total_hojas_leidas": len(book.sheet_names()),
            "total_actividades": len(activities),
            "total_bloques": 0,
            "diagnostico_prueba": diagnostics,
            "escalas_detectadas": [],
            "reglas_por_escala": {"mas": {}, "menos": {}},
            "ambiguedades": ambiguities,
        }
        write_json(Path(args.report), report)
        write_json(Path("storage/logs/debug_prueba_rows.json"), {"rows": debug_rows})
        print(json.dumps(report, ensure_ascii=False, indent=2))
        return 1

    write_json(config_dir / "questions_blocks.json", build_questions_blocks(activities))
    write_json(config_dir / "scoring_rules.json", build_scoring_rules())
    write_json(config_dir / "validity_rules.json", build_validity_rules())
    write_json(config_dir / "percentiles" / "male.json", male)
    write_json(config_dir / "percentiles" / "female.json", female)
    write_json(config_dir / "scales.json", build_scales())
    write_json(config_dir / "excel_mapping.json", build_excel_mapping(book))

    report = {
        "archivo_origen": str(xls_path),
        "total_hojas_leidas": len(book.sheet_names()),
        "total_actividades": len(activities),
        "total_bloques": len(activities) // 3 if activities else 0,
        "diagnostico_prueba": diagnostics,
        "escalas_detectadas": sorted(
            set().union(*(a.mas.keys() for a in activities), *(a.menos.keys() for a in activities))
        ),
        "reglas_por_escala": {
            "mas": rules_count(activities, "mas"),
            "menos": rules_count(activities, "menos"),
        },
        "ambiguedades": ambiguities,
    }
    write_json(Path(args.report), report)
    write_json(Path("storage/logs/debug_prueba_rows.json"), {"rows": debug_rows})

    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

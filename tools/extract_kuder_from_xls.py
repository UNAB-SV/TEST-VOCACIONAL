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


def parse_prueba(sheet: "xlrd.sheet.Sheet", ambiguities: list[str]) -> list[Activity]:
    # Estrategia: detectar triples consecutivos de textos largos por fila,
    # preservando orden natural del Excel.
    candidate_cells: list[tuple[int, int, str]] = []
    for r in range(sheet.nrows):
        for c in range(sheet.ncols):
            text = cell_text(sheet, r, c)
            if len(text) >= 15 and not text.isdigit():
                candidate_cells.append((r, c, text))

    if len(candidate_cells) < 504:
        raise Ambiguity(
            f"No se pudieron detectar 504 actividades en PRUEBA (detectadas={len(candidate_cells)})."
        )

    # Mantener solo primeras 504 actividades en orden top-left para evitar
    # capturar textos auxiliares fuera del instrumento.
    candidate_cells.sort(key=lambda x: (x[0], x[1]))
    selected = candidate_cells[:504]

    activities: list[Activity] = []
    for idx, (r, c, text) in enumerate(selected, start=1):
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

    if len(activities) != 504:
        raise Ambiguity(f"Total de actividades inesperado: {len(activities)} (esperado=504)")

    return activities


def build_questions_blocks(activities: list[Activity]) -> dict[str, Any]:
    blocks: list[dict[str, Any]] = []
    for block_num in range(1, 169):
        block_id = f"B{block_num:03d}"
        chunk = [a for a in activities if a.block_id == block_id]
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
    args = parser.parse_args()

    xls_path = Path(args.xls)
    if not xls_path.exists():
        raise SystemExit(f"No existe el archivo XLS: {xls_path}")

    ambiguities: list[str] = []
    try:
        book = xlrd.open_workbook(str(xls_path), formatting_info=False)
        prueba = detect_prueba_sheet(book)
        activities = parse_prueba(prueba, ambiguities)
        male, female = parse_percentiles(book, ambiguities)
    except Ambiguity as exc:
        ambiguities.append(str(exc))
        activities = []
        male, female = {"sexo": "M", "percentiles": {}}, {"sexo": "F", "percentiles": {}}
        book = xlrd.open_workbook(str(xls_path), formatting_info=False)

    config_dir = Path(args.config_dir)
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

    print(json.dumps(report, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

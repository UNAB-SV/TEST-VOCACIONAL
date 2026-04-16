#!/usr/bin/env python3
"""Audita cobertura de claves MAS/MENOS usando test.xls como fuente de verdad."""
from __future__ import annotations

import json
from pathlib import Path

from extract_kuder_from_xls import SCALE_CODE_MAP, parse_workbook


def main() -> int:
    rows = parse_workbook(Path("test.xls"))["PRUEBA"]
    qpath = Path("config/test-vocacional/questions_blocks.json")
    questions = json.loads(qpath.read_text(encoding="utf-8"))["blocks"]
    activities = [a for b in questions for a in b["actividades"]]

    scale_letter_cols = [(7 + 3 * i, code) for i, code in enumerate(["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "V"])]

    report = {
        "source_of_truth": "test.xls / hoja PRUEBA",
        "totals": {
            "blocks": len(questions),
            "activities": len(activities),
            "with_mas": 0,
            "with_menos": 0,
            "without_keys": 0,
            "only_mas": 0,
            "only_menos": 0,
            "both_keys": 0,
        },
        "explanations": {
            "without_keys": "La fila de Excel no contiene ningún marcador '1' en columnas de MAS ni MENOS.",
            "only_mas": "La fila de Excel contiene marcador '1' para MAS y ninguno para MENOS.",
            "only_menos": "La fila de Excel contiene marcador '1' para MENOS y ninguno para MAS.",
            "both_keys": "La fila de Excel contiene al menos un marcador '1' en MAS y al menos uno en MENOS.",
        },
        "activities_without_keys": [],
        "activities_only_mas": [],
        "activities_only_menos": [],
        "activities_with_both_keys": [],
    }

    for idx, activity in enumerate(activities):
        excel_row = idx + 4
        row = rows.get(excel_row - 1, {})
        text_excel = str(row.get(1, "")).strip()

        mas_codes = [code for col, code in scale_letter_cols if row.get(col - 1) == 1]
        menos_codes = [code for col, code in scale_letter_cols if row.get(col + 1) == 1]

        mas_scales = [SCALE_CODE_MAP[code] for code in mas_codes]
        menos_scales = [SCALE_CODE_MAP[code] for code in menos_codes]

        item = {
            "activity_id": activity["id"],
            "block_id": activity["bloque"],
            "excel_row": excel_row,
            "text": text_excel or activity["texto"],
            "mas_scales": mas_scales,
            "menos_scales": menos_scales,
        }

        has_mas = len(mas_scales) > 0
        has_menos = len(menos_scales) > 0

        if has_mas:
            report["totals"]["with_mas"] += 1
        if has_menos:
            report["totals"]["with_menos"] += 1

        if has_mas and has_menos:
            report["totals"]["both_keys"] += 1
            report["activities_with_both_keys"].append(item)
        elif has_mas:
            report["totals"]["only_mas"] += 1
            report["activities_only_mas"].append(item)
        elif has_menos:
            report["totals"]["only_menos"] += 1
            report["activities_only_menos"].append(item)
        else:
            report["totals"]["without_keys"] += 1
            report["activities_without_keys"].append(item)

    out_path = Path("storage/logs/scoring_key_audit.json")
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print(json.dumps(report["totals"], ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

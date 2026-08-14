#!/usr/bin/env python3
"""Build the reviewed SWPro catalog seed and product-only image candidates.

The script never stores page screenshots. It exports only independent PDF image
objects with a transparent background and marks them as candidates until a
human approves them in the admin area.
"""

from __future__ import annotations

import argparse
import io
import json
import re
import shutil
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any

import pdfplumber
from PIL import Image
from pypdf import PdfReader


SKU_RE = re.compile(r"(?<!\d)(\d{5,6})(?!\d)")
EXCLUDED_CODES = {
    "042026", "114433", "114488", "215214", "217216", "219218",
    "221220", "223222", "225224", "30257", "620240", "854801",
}
# Independent PDF objects that are ingredients, decorative molecules, flowers,
# people or advertising compositions rather than a clean product packshot.
EXCLUDED_IMAGE_SKUS = {
    "108371", "422766", "422772", "423993", "424434", "424437", "425181", "425403",
    "425534", "425687", "426985", "427649", "427651", "427652", "427653",
    "427846", "427847", "428799", "500484", "500572", "500660", "500663",
    "500670", "500714", "501065", "501143", "501182", "501329",
    "501335", "501366", "501388", "501434",
}
GENERIC_TITLES = {
    "ВЫСОКАЯ КОНЦЕНТРАЦИЯ", "МАКСИМАЛЬНАЯ КОНЦЕНТРАЦИЯ", "НОВИНКА",
    "СКОРО В ПРОДАЖЕ", "ПАРФЮМЕРНАЯ ВОДА", "ПРИРОДНАЯ",
    "ПРОФИЛАКТИЧЕСКАЯ", "СОСТАВ", "КОГДА АКТУАЛЬНО",
}
SIGNALS: dict[str, tuple[str, ...]] = {
    "energy-fatigue": ("энерг", "устал", "тонус", "работоспособ"),
    "stress-sleep": ("стресс", "сон", "расслаб", "напряж", "антистресс"),
    "immunity": ("иммун", "простуд", "защитных сил", "антивирус"),
    "digestion-microbiome": ("пищевар", "кишеч", "микрофлор", "микробиом", "желуд"),
    "weight-metabolism": ("вес", "стройност", "метабол", "аппетит", "углевод"),
    "heart-vessels": ("серд", "сосуд", "кровообращ", "холестерин", "омега-3"),
    "brain-vision": ("мозг", "памят", "концентрац", "зрени", "лютеин"),
    "bones-joints": ("кост", "сустав", "связок", "кальци", "хондро"),
    "women-health": ("женск", "цикл", "менопауз", "берем", "кормящ"),
    "skin-hair-nails": ("кож", "волос", "ногт", "коллаген", "beauty"),
    "children": ("детск", "детям", "ребен"),
    "sport-recovery": ("спорт", "трениров", "вынослив", "протеин", "восстанов"),
    "face-skin": ("кожи лица", "морщин", "акне", "сыворотка", "тоник", "крем для лица"),
    "hair-scalp": ("кожи головы", "роста волос", "перхот", "шампун", "кондиционер"),
    "fragrance-preferences": ("парфюм", "аромат", "ключевые ноты", "цветочный", "древесный"),
}
TITLE_OVERRIDES = {
    "501466": "PROBIOTICS NUTRITION COMFORT", "501462": "PROBIOTICS SENIOR",
    "501822": "PROBIOTICS COLOBEN", "501737": "PROBIOTICS PROBIOVITAL",
    "501637": "MEN'S BOX", "501638": "Ежовик гребенчатый и кордицепс",
    "501681": "MAMA BOX (Беременность)", "501682": "MAMA BOX (Грудное вскармливание)",
    "500467": "LITE STEP BOX", "500952": "GLUCO BOX", "500175": "IQ BOX", "500361": "VISION BOX",
    "500526": "IMMUNO BOX", "500443": "PULSE BOX", "500172": "BEAUTY BOX", "500931": "RELAX BOX",
    "501200": "D-манноза и северная клюква", "501330": "Хронолонг", "501405": "Дикий ямс",
    "501616": "Новомин-L", "501420": "Натуральный бета-каротин и витамин E",
    "501620": "Totally Vegan Omega-3", "501641": "N-ацетил-L-цистеин", "501291": "Мультихелат магния",
    "501418": "S-ацетил-L-глутатион", "501419": "Коэнзим Q10",
    "501233": "Витамин C и рутин", "501234": "Витамин K2",
    "500577": "Северная клюква и B-витамины", "500625": "Бетаин и B-витамины",
    "500652": "Витамины красоты", "500676": "Витамины с кальцием",
    "501236": "Альфа-липоевая кислота", "500626": "Диосмин и рутин",
    "501429": "Натуральный витамин E", "501644": "Витамин D3 Max", "500820": "Витамин D3",
    "500954": "Органический германий", "500630": "Органический селен",
    "500914": "Органический кальций", "500628": "Органический кальций",
    "500629": "Органический магний", "500658": "Органический йод",
    "500631": "Органический цинк", "500627": "Органическое железо", "501569": "Органический кремний",
    "500657": "Валериана и мелисса", "500656": "Медвежьи ушки и брусника",
    "501141": "Биодоступный куркумин", "501306": "Мака перуанская",
    "501672": "Комплекс растительных горечей", "501431": "Пустырник и мята",
    "500632": "Pure Life - очищающий фитосорбент", "500633": "Joint Comfort - суставной фитосорбент",
    "500634": "Intestinal Defense - кишечный фитосорбент", "400237": "Природный инулиновый концентрат",
    "500660": "Натуральный бета-каротин и облепиха", "500688": "Лютеин и зеаксантин",
    "500662": "Сибирский лён и омега-3", "500689": "Ликопин и омега-3",
    "500659": "Бораго и амарант", "500661": "Северная омега-3",
    "419968": "NONUM 9", "422767": "DEMON DU CIEL", "108217": "FLUIDES BLACK CHERRY & TONKA BEANS",
    "425246": "ARC-EN-CIEL EMERALD",
}


@dataclass
class Cell:
    page: int
    bbox: tuple[float, float, float, float]
    codes: list[str]
    text: str


def clean_space(value: str) -> str:
    value = value.replace("\u00ad", "").replace("­", "")
    value = re.sub(r"\s+", " ", value)
    return value.strip(" |,;:-")


def sql(value: Any) -> str:
    if value is None:
        return "NULL"
    if isinstance(value, bool):
        return "1" if value else "0"
    if isinstance(value, (int, float)):
        return str(value)
    return "'" + str(value).replace("\\", "\\\\").replace("'", "''") + "'"


def slugify(value: str) -> str:
    table = str.maketrans(
        "абвгдеёжзийклмнопрстуфхцчшщъыьэюя",
        "abvgdeejzijklmnoprstufhccss_y_eua",
    )
    value = value.lower().translate(table)
    value = re.sub(r"[^a-z0-9]+", "-", value).strip("-")
    return value[:150] or "catalog-product"


def valid_code(text: str) -> str | None:
    code = text.strip(".,|I")
    if not re.fullmatch(r"\d{5,6}", code):
        return None
    if code in EXCLUDED_CODES or code.startswith(("19", "20")):
        return None
    return code


def upper_ratio(value: str) -> float:
    letters = [char for char in value if char.isalpha()]
    return sum(char.isupper() for char in letters) / len(letters) if letters else 0.0


def extract_cells(page: pdfplumber.page.Page, page_number: int) -> list[Cell]:
    words = page.extract_words(x_tolerance=2, y_tolerance=3)
    code_words = [(word, valid_code(word["text"])) for word in words]
    code_words = [(word, code) for word, code in code_words if code]
    clusters: list[dict[str, Any]] = []
    for word, code in sorted(code_words, key=lambda item: (item[0]["x0"], item[0]["top"])):
        center_x = (word["x0"] + word["x1"]) / 2
        cluster = next((item for item in clusters if abs(item["x"] - center_x) < 35), None)
        if cluster is None:
            cluster = {"x": center_x, "items": []}
            clusters.append(cluster)
        cluster["items"].append((word, code))
        cluster["x"] = sum((item[0]["x0"] + item[0]["x1"]) / 2 for item in cluster["items"]) / len(cluster["items"])
    clusters.sort(key=lambda item: item["x"])

    cells: list[Cell] = []
    for index, cluster in enumerate(clusters):
        left = 0 if index == 0 else (clusters[index - 1]["x"] + cluster["x"]) / 2
        right = page.width if index == len(clusters) - 1 else (cluster["x"] + clusters[index + 1]["x"]) / 2
        groups: list[dict[str, Any]] = []
        for word, code in sorted(cluster["items"], key=lambda item: item[0]["top"]):
            center_y = (word["top"] + word["bottom"]) / 2
            if not groups or center_y - groups[-1]["last"] > 55:
                groups.append({"items": [(word, code)], "first": center_y, "last": center_y})
            else:
                groups[-1]["items"].append((word, code))
                groups[-1]["last"] = center_y
        for group_index, group in enumerate(groups):
            top = 0 if group_index == 0 else (groups[group_index - 1]["last"] + group["first"]) / 2
            bottom = page.height if group_index == len(groups) - 1 else (group["last"] + groups[group_index + 1]["first"]) / 2
            top = max(top, group["first"] - 180)
            bottom = min(bottom, group["last"] + 190)
            bbox = (max(0, left), max(0, top), min(page.width, right), min(page.height, bottom))
            text = page.crop(bbox).extract_text(x_tolerance=2, y_tolerance=3) or ""
            codes: list[str] = []
            for _, code in group["items"]:
                if code not in codes:
                    codes.append(code)
            cells.append(Cell(page_number, bbox, codes, text))
    return cells


def title_from_cell(cell: Cell) -> str:
    lines = [clean_space(line) for line in cell.text.splitlines() if clean_space(line)]
    code_indexes = [index for index, line in enumerate(lines) if any(re.search(rf"(?<!\d){re.escape(code)}(?!\d)", line) for code in cell.codes)]
    if not code_indexes:
        return f"Продукт {cell.codes[0]}"
    first, last = min(code_indexes), max(code_indexes)
    candidates: list[tuple[float, int, str]] = []
    for index, line in enumerate(lines):
        cleaned = re.sub(r"(?<!\d)\d{5,6}(?!\d).*", "", line).strip(" ,|")
        upper = cleaned.upper()
        if not 3 <= len(cleaned) <= 110 or upper_ratio(cleaned) < 0.70:
            continue
        if any(value in upper for value in ("₽", "СОСТАВ:", "ПРОДУКЦИЯ SIBERIAN", "КАТАЛОГ", "ФИЛЬТР-ПАКЕТ")):
            continue
        if upper in GENERIC_TITLES:
            continue
        distance = min(abs(index - first), abs(index - last))
        side_bonus = 3 if index > last and index - last <= 4 else 0
        if cell.page >= 82 and index < first and first - index <= 16:
            side_bonus += 3
        score = 12 - distance + side_bonus + (1 if len(cleaned) < 65 else 0)
        candidates.append((score, index, cleaned))
    if not candidates:
        return f"Продукт {cell.codes[0]}"
    _, index, title = max(candidates)
    adjacent = index + 1
    if adjacent < len(lines):
        next_line = lines[adjacent]
        if 3 <= len(next_line) <= 65 and upper_ratio(next_line) > 0.78 and not SKU_RE.search(next_line) and next_line.upper() not in GENERIC_TITLES:
            if not any(value in next_line.upper() for value in ("₽", "СОСТАВ", "КАПСУЛ", "ТАБЛЕТ")):
                title += " " + next_line
    title = clean_space(re.sub(r"^[•✔\d.]+\s*", "", title))
    title = re.sub(r"\b(ВЫСОКАЯ|МАКСИМАЛЬНАЯ) КОНЦЕНТРАЦИЯ:?\b", "", title, flags=re.I)
    return clean_space(title)[:190] or f"Продукт {cell.codes[0]}"


def variant_data(cell: Cell, code: str) -> dict[str, Any]:
    flat = clean_space(cell.text)
    match = re.search(rf"(?<!\d){re.escape(code)}(?!\d)(.{{0,100}})", flat, flags=re.I)
    tail = match.group(1) if match else flat
    volume_match = re.search(r"(\d+(?:[.,]\d+)?\s*(?:мл|г|кг|капсул(?:ы)?|таблет(?:ок|ки)?|пакет(?:ов|а)?|саше|фильтр-пакет(?:ов|а)?))", tail, flags=re.I)
    price_match = re.search(r"(\d{2,5})\s*₽", tail)
    volume = clean_space(volume_match.group(1)) if volume_match else None
    price = int(price_match.group(1)) if price_match else None
    return {
        "sku": code,
        "title": None,
        "volume_text": volume,
        "price": price,
        "currency": "RUB",
        "is_sample": bool(volume and re.search(r"(?:1[.,]5|2)\s*мл", volume, flags=re.I)),
    }


def product_section(page: int) -> tuple[str, str]:
    if 58 <= page <= 63:
        return "sport-nutrition", "food"
    if 4 <= page <= 53:
        return "health-nutrition", "supplement"
    if 54 <= page <= 57:
        return "health-nutrition", "food"
    if 64 <= page <= 81 or 98 <= page <= 104:
        return "face-care", "cosmetic"
    if 82 <= page <= 97:
        return "fragrance", "fragrance"
    if 105 <= page <= 109:
        return "hair-care", "personal_care"
    if 110 <= page <= 114:
        return "body-care", "personal_care"
    return "personal-hygiene", "personal_care"


def product_text(cell: Cell, title: str) -> tuple[str, str, str | None]:
    text = clean_space(cell.text)
    text = re.sub(r"Продукция Siberian Wellness\s*\|?\s*\d*", "", text, flags=re.I)
    for code in cell.codes:
        text = re.sub(rf"(?<!\d){re.escape(code)}(?!\d)", "", text)
    text = clean_space(text)
    composition = None
    comp = re.search(r"Состав:\s*(.+?)(?=(?:\d+(?:[.,]\d+)?\s*(?:мл|г|капсул|таблет|пакет)|$))", text, flags=re.I)
    if comp:
        composition = clean_space(comp.group(1))[:4000]
    description = text
    if title and title.lower() in description.lower():
        start = description.lower().find(title.lower()) + len(title)
        description = clean_space(description[start:])
    description = re.sub(r"Состав:.*", "", description, flags=re.I)
    description = re.sub(r"\d+(?:[.,]\d+)?\s*(?:мл|г|капсул\w*|таблет\w*|пакет\w*)\s*\d*\s*₽?.*", "", description, flags=re.I)
    description = clean_space(description)
    return description[:900], text[:10000], composition


def signal_slugs(text: str) -> list[str]:
    value = text.lower()
    return [slug for slug, keywords in SIGNALS.items() if any(keyword in value for keyword in keywords)]


def image_candidates(reader_page: Any, plumber_page: Any) -> list[dict[str, Any]]:
    files = {Path(item.name).stem: item for item in reader_page.images}
    candidates = []
    page_area = plumber_page.width * plumber_page.height
    for item in plumber_page.images:
        image_file = files.get(str(item.get("name", "")))
        if image_file is None:
            continue
        pil = image_file.image.convert("RGBA")
        if pil.width < 80 or pil.height < 80:
            continue
        bbox = (float(item["x0"]), float(item["top"]), float(item["x1"]), float(item["bottom"]))
        area_ratio = max(0.0, (bbox[2] - bbox[0]) * (bbox[3] - bbox[1])) / page_area
        if not 0.002 <= area_ratio <= 0.18:
            continue
        alpha = pil.getchannel("A")
        lo, hi = alpha.getextrema()
        if lo == hi == 255:
            continue
        candidates.append({"name": Path(image_file.name).stem, "bbox": bbox, "image": pil})
    return candidates


def overlap_score(a: tuple[float, float, float, float], b: tuple[float, float, float, float]) -> float:
    width = max(0.0, min(a[2], b[2]) - max(a[0], b[0]))
    height = max(0.0, min(a[3], b[3]) - max(a[1], b[1]))
    intersection = width * height
    image_area = max(1.0, (b[2] - b[0]) * (b[3] - b[1]))
    return intersection / image_area


def export_packshot(cell: Cell, candidates: list[dict[str, Any]], output_dir: Path, key: str) -> str | None:
    if any(code in EXCLUDED_IMAGE_SKUS for code in cell.codes):
        return None
    ranked = sorted(((overlap_score(cell.bbox, item["bbox"]), item) for item in candidates), key=lambda pair: pair[0], reverse=True)
    if not ranked or ranked[0][0] < 0.60:
        return None
    image = ranked[0][1]["image"]
    alpha_box = image.getchannel("A").getbbox()
    if alpha_box:
        image = image.crop(alpha_box)
    image.thumbnail((900, 900), Image.Resampling.LANCZOS)
    output_dir.mkdir(parents=True, exist_ok=True)
    filename = f"{key}.webp"
    image.save(output_dir / filename, "WEBP", quality=88, method=6)
    return "/admin/uploads/products/catalog-2026/" + filename


def export_named_packshot(reader_page: Any, image_name: str, output_dir: Path, key: str) -> str:
    image_file = next(item for item in reader_page.images if Path(item.name).stem == image_name)
    image = image_file.image.convert("RGBA")
    alpha_box = image.getchannel("A").getbbox()
    if alpha_box:
        image = image.crop(alpha_box)
    image.thumbnail((900, 900), Image.Resampling.LANCZOS)
    output_dir.mkdir(parents=True, exist_ok=True)
    filename = f"{key}.webp"
    image.save(output_dir / filename, "WEBP", quality=88, method=6)
    return "/admin/uploads/products/catalog-2026/" + filename


def add_manual_products(products: list[dict[str, Any]], reader: PdfReader, image_dir: Path) -> None:
    existing = {variant["sku"] for product in products for variant in product["variants"]}
    manual = [
        {
            "title": "Глюкозамин и хондроитин", "sku": "500651", "page": 30,
            "category": "health-nutrition", "kind": "supplement", "price": None,
            "volume": "30 капсул", "image_page": 30, "image_name": "Im5",
            "description": "Комплекс для поддержки суставов, хрящей и связок. Информация перенесена из каталога и требует проверки официальной инструкции.",
        },
        {
            "title": "Корень - бальзам широкого спектра действия", "sku": "409066", "extra_skus": ["402860"], "page": 51,
            "category": "body-care", "kind": "personal_care", "price": 550,
            "volume": "250 мл", "image_page": 51, "image_name": "Im3",
            "description": "Бальзам широкого спектра действия для наружного применения.",
        },
    ]
    for item in manual:
        if item["sku"] in existing:
            continue
        slug = f"ru-2026-{slugify(item['title'])}-{item['sku']}"
        image_path = export_named_packshot(reader.pages[item["image_page"] - 1], item["image_name"], image_dir, slug)
        variants = [{
            "sku": sku, "title": None, "volume_text": item["volume"], "price": item["price"],
            "currency": "RUB", "is_sample": False,
        } for sku in [item["sku"], *item.get("extra_skus", [])]]
        is_health = item["kind"] in {"supplement", "food"}
        products.append({
            "title": item["title"], "slug": slug, "category_slug": item["category"], "kind": item["kind"],
            "catalog_sku": item["sku"], "catalog_page": item["page"], "short_description": item["description"],
            "full_description": item["description"], "composition": None, "price": item["price"], "image_path": image_path,
            "image_review_status": "candidate", "safety_review_status": "catalog_only" if is_health else "not_required",
            "content_status": "review" if is_health else "approved", "ai_enabled": not is_health,
            "recommendation_notice": "Информация носит ознакомительный характер. Уточните способ применения и ограничения у консультанта или специалиста." if is_health else "Учитывайте индивидуальную чувствительность.",
            "signals": signal_slugs(item["title"] + " " + item["description"]), "variants": variants,
        })


def build_catalog(pdf_path: Path, image_dir: Path) -> list[dict[str, Any]]:
    reader = PdfReader(str(pdf_path))
    products: list[dict[str, Any]] = []
    if image_dir.exists():
        shutil.rmtree(image_dir)
    with pdfplumber.open(str(pdf_path)) as document:
        for page_number in range(4, len(document.pages) + 1):
            plumber_page = document.pages[page_number - 1]
            candidates = image_candidates(reader.pages[page_number - 1], plumber_page)
            for cell in extract_cells(plumber_page, page_number):
                title = TITLE_OVERRIDES.get(cell.codes[0], title_from_cell(cell))
                category, kind = product_section(page_number)
                short, full, composition = product_text(cell, title)
                variants = [variant_data(cell, code) for code in cell.codes]
                slug = f"ru-2026-{slugify(title)}-{cell.codes[0]}"
                image_path = export_packshot(cell, candidates, image_dir, slug)
                is_health = kind in {"supplement", "food"}
                products.append({
                    "title": title,
                    "slug": slug,
                    "category_slug": category,
                    "kind": kind,
                    "catalog_sku": cell.codes[0],
                    "catalog_page": page_number,
                    "short_description": short,
                    "full_description": full,
                    "composition": composition,
                    "price": variants[0]["price"],
                    "image_path": image_path,
                    "image_review_status": "candidate" if image_path else "missing",
                    "safety_review_status": "catalog_only" if is_health else "not_required",
                    "content_status": "review" if is_health else "approved",
                    "ai_enabled": not is_health,
                    "recommendation_notice": (
                        "Информация носит ознакомительный характер. Перед применением уточните способ приёма, ограничения и совместимость у консультанта или специалиста."
                        if is_health else "Подбор выполнен по информации и предпочтениям клиента. Учитывайте индивидуальную чувствительность."
                    ),
                    "signals": signal_slugs(title + " " + short + " " + full),
                    "variants": variants,
                })
    add_manual_products(products, reader, image_dir)
    return products


def write_sql(products: list[dict[str, Any]], path: Path) -> None:
    lines = [
        "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;",
        "SET @catalog_source := 'Siberian Wellness RU 2026 (042026)';",
        "",
    ]
    for index, product in enumerate(products, start=1):
        allowed_claims = product["short_description"] or product["title"]
        warning = product["recommendation_notice"] if product["kind"] in {"supplement", "food"} else None
        values = {
            "category_id": f"(SELECT id FROM product_categories WHERE slug = {sql(product['category_slug'])} LIMIT 1)",
            "title": sql(product["title"]),
            "slug": sql(product["slug"]),
            "catalog_sku": sql(product["catalog_sku"]),
            "catalog_source": "@catalog_source",
            "catalog_page": str(product["catalog_page"]),
            "product_kind": sql(product["kind"]),
            "safety_review_status": sql(product["safety_review_status"]),
            "image_review_status": sql(product["image_review_status"]),
            "recommendation_notice": sql(product["recommendation_notice"]),
            "short_description": sql(product["short_description"]),
            "full_description": sql(product["full_description"]),
            "composition": sql(product["composition"]),
            "warning_text": sql(warning),
            "allowed_claims": sql(allowed_claims),
            "source_urls": sql(f"Каталог Siberian Wellness RU 2026, стр. PDF {product['catalog_page']}"),
            "image_path": sql(product["image_path"]),
            "price": sql(product["price"]),
            "ai_enabled": "1" if product["ai_enabled"] else "0",
            "content_status": sql(product["content_status"]),
            "is_active": "1",
            "sort_order": str(1000 + index),
        }
        columns = ", ".join(values)
        value_sql = ", ".join(values.values())
        lines.append(f"INSERT INTO products ({columns}) VALUES ({value_sql}) ON DUPLICATE KEY UPDATE title = VALUES(title), category_id = VALUES(category_id), catalog_sku = VALUES(catalog_sku), catalog_source = VALUES(catalog_source), catalog_page = VALUES(catalog_page), product_kind = VALUES(product_kind), safety_review_status = VALUES(safety_review_status), image_review_status = VALUES(image_review_status), recommendation_notice = VALUES(recommendation_notice), short_description = VALUES(short_description), full_description = VALUES(full_description), composition = VALUES(composition), warning_text = VALUES(warning_text), allowed_claims = VALUES(allowed_claims), source_urls = VALUES(source_urls), image_path = VALUES(image_path), price = VALUES(price), ai_enabled = VALUES(ai_enabled), content_status = VALUES(content_status), is_active = 1;")
        for variant_index, variant in enumerate(product["variants"], start=1):
            lines.append(
                "INSERT INTO product_variants (product_id, sku, title, volume_text, price, currency, image_path, is_sample, is_active, sort_order) VALUES "
                f"((SELECT id FROM products WHERE slug = {sql(product['slug'])} LIMIT 1), {sql(variant['sku'])}, {sql(variant['title'])}, {sql(variant['volume_text'])}, {sql(variant['price'])}, 'RUB', {sql(product['image_path'])}, {1 if variant['is_sample'] else 0}, 1, {variant_index * 10}) "
                "ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), title = VALUES(title), volume_text = VALUES(volume_text), price = VALUES(price), image_path = VALUES(image_path), is_sample = VALUES(is_sample), is_active = 1;"
            )
        if product["image_path"]:
            lines.append(
                "INSERT INTO product_media (product_id, media_type, file_path, source_page, review_status, is_primary) VALUES "
                f"((SELECT id FROM products WHERE slug = {sql(product['slug'])} LIMIT 1), 'product_packshot', {sql(product['image_path'])}, {product['catalog_page']}, 'candidate', 1) "
                "ON DUPLICATE KEY UPDATE product_id = VALUES(product_id), source_page = VALUES(source_page);"
            )
        for signal in product["signals"]:
            approved = 0 if product["kind"] in {"supplement", "food"} else 1
            lines.append(
                "INSERT INTO product_signal_links (product_id, signal_id, match_type, weight, rationale, is_approved) VALUES "
                f"((SELECT id FROM products WHERE slug = {sql(product['slug'])} LIMIT 1), (SELECT id FROM recommendation_signals WHERE slug = {sql(signal)} LIMIT 1), 'supports', 100, {sql('Соответствует теме запроса по утверждённой карточке каталога.')}, {approved}) "
                "ON DUPLICATE KEY UPDATE weight = VALUES(weight), rationale = VALUES(rationale), is_approved = VALUES(is_approved);"
            )
        lines.append("")
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("\n".join(lines), encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("pdf", type=Path)
    parser.add_argument("--manifest", type=Path, default=Path("database/catalog/ru_2026_products.json"))
    parser.add_argument("--sql", type=Path, default=Path("database/migrations/20260814_21_import_ru_2026_catalog.sql"))
    parser.add_argument("--images", type=Path, default=Path("admin/uploads/products/catalog-2026"))
    args = parser.parse_args()
    if not args.pdf.is_file():
        parser.error(f"PDF not found: {args.pdf}")
    products = build_catalog(args.pdf, args.images)
    args.manifest.parent.mkdir(parents=True, exist_ok=True)
    args.manifest.write_text(json.dumps(products, ensure_ascii=False, indent=2), encoding="utf-8")
    write_sql(products, args.sql)
    print(json.dumps({
        "products": len(products),
        "variants": sum(len(product["variants"]) for product in products),
        "images": sum(bool(product["image_path"]) for product in products),
        "needs_title_review": sum(product["title"].startswith("Продукт ") for product in products),
    }, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    sys.exit(main())

from bot.core.i18n import tr

MEDICAL_DISCLAIMER = tr("medical_disclaimer")

MAIN_MENU = [
    tr("menu.tests"),
    tr("menu.recommendations"),
    tr("menu.products"),
    tr("menu.contact_manager"),
    tr("menu.profile"),
    tr("menu.help"),
]


def welcome_text(first_name: str | None = None) -> str:
    name = f", {first_name}" if first_name else ""
    return tr("welcome", name=name, disclaimer=MEDICAL_DISCLAIMER)

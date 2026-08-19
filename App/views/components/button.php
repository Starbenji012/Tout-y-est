<?php

$buttonConfig = $button ?? [];
$buttonLabel = trim((string) ($buttonConfig['label'] ?? ''));
$buttonVariant = (string) ($buttonConfig['variant'] ?? 'primary');
$buttonVariants = ['primary', 'secondary', 'outline', 'ghost'];
$buttonVariant = in_array($buttonVariant, $buttonVariants, true) ? $buttonVariant : 'primary';
$buttonHref = isset($buttonConfig['href']) ? (string) $buttonConfig['href'] : null;
$buttonType = (string) ($buttonConfig['type'] ?? 'button');
$buttonType = in_array($buttonType, ['button', 'submit', 'reset'], true) ? $buttonType : 'button';
$buttonIcon = preg_match('/^[a-z0-9-]+$/', (string) ($buttonConfig['icon'] ?? '')) ? (string) $buttonConfig['icon'] : '';
$buttonIconPosition = ($buttonConfig['iconPosition'] ?? 'end') === 'start' ? 'start' : 'end';
$buttonClass = trim('btn btn-' . $buttonVariant . ' ' . (string) ($buttonConfig['class'] ?? ''));
$buttonAttributes = $buttonConfig['attributes'] ?? [];
$buttonReservedAttributes = ['class', 'href', 'type'];

if ($buttonLabel === '') {
    return;
}
?>

<<?= $buttonHref !== null ? 'a' : 'button' ?>
    class="<?= htmlspecialchars($buttonClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
    <?= $buttonHref !== null ? 'href="' . htmlspecialchars($buttonHref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : 'type="' . $buttonType . '"' ?>
    <?php foreach ($buttonAttributes as $attribute => $value): ?>
        <?php if (!in_array($attribute, $buttonReservedAttributes, true) && preg_match('/^[a-zA-Z_:][a-zA-Z0-9:._-]*$/', (string) $attribute) && $value !== false && $value !== null): ?>
            <?= htmlspecialchars((string) $attribute, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><?= $value === true ? '' : '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' ?>
        <?php endif; ?>
    <?php endforeach; ?>
>
    <?php if ($buttonIcon !== '' && $buttonIconPosition === 'start'): ?>
        <i data-lucide="<?= $buttonIcon ?>" aria-hidden="true"></i>
    <?php endif; ?>
    <?= htmlspecialchars($buttonLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
    <?php if ($buttonIcon !== '' && $buttonIconPosition === 'end'): ?>
        <i data-lucide="<?= $buttonIcon ?>" aria-hidden="true"></i>
    <?php endif; ?>
</<?= $buttonHref !== null ? 'a' : 'button' ?>>

<?php unset($button, $buttonConfig, $buttonLabel, $buttonVariant, $buttonVariants, $buttonHref, $buttonType, $buttonIcon, $buttonIconPosition, $buttonClass, $buttonAttributes, $buttonReservedAttributes); ?>

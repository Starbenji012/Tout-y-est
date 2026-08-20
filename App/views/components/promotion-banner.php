<?php

$promotionBannerConfig = $promotionBanner ?? [];
$promotionBannerId = (string) ($promotionBannerConfig['id'] ?? 'promotion-banner-title');
$promotionBannerTitle = (string) ($promotionBannerConfig['title'] ?? '');
$promotionBannerText = (string) ($promotionBannerConfig['text'] ?? '');
$promotionBannerAction = $promotionBannerConfig['action'] ?? null;
$promotionBannerCountdown = $promotionBannerConfig['countdown'] ?? [
    ['value' => '00', 'label' => 'Jours'],
    ['value' => '00', 'label' => 'Heures'],
    ['value' => '00', 'label' => 'Minutes'],
    ['value' => '00', 'label' => 'Secondes'],
];
?>

<aside class="promotion-banner" aria-labelledby="<?= htmlspecialchars($promotionBannerId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-aos="soft-right">
    <div class="promotion-banner__content">
        <h3 class="promotion-banner__title" id="<?= htmlspecialchars($promotionBannerId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <?= htmlspecialchars($promotionBannerTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </h3>
        <p class="promotion-banner__text"><?= htmlspecialchars($promotionBannerText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>

        <?php if (is_array($promotionBannerAction)): ?>
            <?php $button = $promotionBannerAction; ?>
            <?php require __DIR__ . '/button.php'; ?>
        <?php endif; ?>
    </div>

    <div class="promotion-banner__timer" aria-label="Compte à rebours promotionnel bientôt disponible">
        <p class="promotion-banner__timer-label">Fin de l'offre dans</p>
        <div class="promotion-banner__countdown" aria-hidden="true">
            <?php foreach ($promotionBannerCountdown as $countdownItem): ?>
                <span class="promotion-banner__countdown-item">
                    <strong><?= htmlspecialchars((string) ($countdownItem['value'] ?? '00'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars((string) ($countdownItem['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</aside>

<?php unset($promotionBanner, $promotionBannerConfig, $promotionBannerId, $promotionBannerTitle, $promotionBannerText, $promotionBannerAction, $promotionBannerCountdown, $countdownItem, $button); ?>

<footer class="site-footer">
    <div class="container footer-container">
        <div class="footer-grid<?= $siteContactLinks !== [] || $siteSocialLinks !== [] ? ' footer-grid--with-contact' : '' ?>" data-motion="section">
            <section class="footer-about-container" id="footer-about" aria-labelledby="footer-about-title">
                <h2 class="visually-hidden" id="footer-about-title">À propos de Tout y est</h2>
                <div class="footer-brand-container">
                    <?php require __DIR__ . '/brand.php'; ?>
                </div>
                <p class="footer-description">
                    Une boutique pensée pour réunir simplement les produits utiles à votre quotidien.
                </p>
                <p class="footer-slogan">Tout ce qu'il vous faut, au même endroit.</p>
            </section>

            <nav class="footer-navigation-container" aria-labelledby="footer-navigation-title">
                <h2 class="footer-title" id="footer-navigation-title">Navigation rapide</h2>
                <ul class="footer-links">
                    <li><a href="/">Accueil</a></li>
                    <li><a href="/boutique">Boutique</a></li>
                    <li><a href="/promotions">Promotions</a></li>
                    <li><a href="/#footer-about">À propos</a></li>
                </ul>
            </nav>

            <?php if ($siteContactLinks !== [] || $siteSocialLinks !== []): ?>
                <section class="footer-contact-container" aria-labelledby="footer-contact-title">
                    <h2 class="footer-title" id="footer-contact-title">Contact</h2>
                    <?php if ($siteContactLinks !== []): ?>
                        <address class="footer-contact-list">
                            <?php foreach ($siteContactLinks as $siteLink): ?>
                                <?php $siteLinkIcon = preg_match('/^[a-z0-9-]+$/', (string) ($siteLink['icon'] ?? '')) ? (string) $siteLink['icon'] : 'link'; ?>
                                <a href="<?= htmlspecialchars((string) $siteLink['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                    <i data-lucide="<?= $siteLinkIcon ?>" aria-hidden="true"></i>
                                    <?= htmlspecialchars((string) $siteLink['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                </a>
                            <?php endforeach; ?>
                        </address>
                    <?php endif; ?>
                    <?php if ($siteSocialLinks !== []): ?>
                        <div class="footer-social-links" aria-label="Réseaux sociaux">
                            <?php foreach ($siteSocialLinks as $siteLink): ?>
                                <?php $siteLinkIcon = preg_match('/^[a-z0-9-]+$/', (string) ($siteLink['icon'] ?? '')) ? (string) $siteLink['icon'] : 'link'; ?>
                                <a href="<?= htmlspecialchars((string) $siteLink['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" aria-label="<?= htmlspecialchars((string) $siteLink['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                    <i data-lucide="<?= $siteLinkIcon ?>" aria-hidden="true"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-copyright-container">
        <div class="container footer-bottom-container">
            <p>© <time datetime="2026">2026</time> Tout y est</p>
        </div>
    </div>
</footer>

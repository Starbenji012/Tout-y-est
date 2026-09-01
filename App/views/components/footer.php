<footer class="site-footer">
    <div class="container footer-container">
        <div class="footer-grid" data-motion="section">
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
                    <li><a href="/#footer-contact">Contact</a></li>
                </ul>
            </nav>

            <section class="footer-information-container" aria-labelledby="footer-information-title">
                <h2 class="footer-title" id="footer-information-title">Informations utiles</h2>
                <ul class="footer-links">
                    <li><span class="footer-link-placeholder">Livraison</span></li>
                    <li><span class="footer-link-placeholder">Modes de paiement</span></li>
                    <li><span class="footer-link-placeholder">Politique de retour</span></li>
                    <li><span class="footer-link-placeholder">Conditions générales</span></li>
                    <li><span class="footer-link-placeholder">FAQ</span></li>
                </ul>
            </section>

            <section class="footer-contact-container" id="footer-contact" aria-labelledby="footer-contact-title">
                <h2 class="footer-title" id="footer-contact-title">Contact</h2>
                <address class="footer-contact-list">
                    <span class="footer-contact-placeholder">
                        <i data-lucide="phone" aria-hidden="true"></i>
                        <span>Téléphone</span>
                    </span>
                    <span class="footer-contact-placeholder">
                        <i data-lucide="message-circle" aria-hidden="true"></i>
                        <span>WhatsApp</span>
                    </span>
                    <span class="footer-contact-placeholder">
                        <i data-lucide="mail" aria-hidden="true"></i>
                        <span>E-mail</span>
                    </span>
                    <span class="footer-contact-address">
                        <i data-lucide="map-pin" aria-hidden="true"></i>
                        <span>Adresse à renseigner</span>
                    </span>
                </address>

                <div class="footer-social-container">
                    <p class="footer-social-title">Suivez-nous</p>
                    <div class="footer-social-links">
                        <span class="footer-social-placeholder" aria-label="Facebook, lien à venir">
                            <i data-lucide="thumbs-up" aria-hidden="true"></i>
                        </span>
                        <span class="footer-social-placeholder" aria-label="Instagram, lien à venir">
                            <i data-lucide="camera" aria-hidden="true"></i>
                        </span>
                        <span class="footer-social-placeholder" aria-label="TikTok, lien à venir">
                            <i data-lucide="music-2" aria-hidden="true"></i>
                        </span>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="footer-copyright-container">
        <div class="container footer-bottom-container">
            <p>© <time datetime="2026">2026</time> Tout y est</p>
            <ul class="footer-assurances" aria-label="Nos engagements">
                <li>
                    <i data-lucide="shield-check" aria-hidden="true"></i>
                    Paiement sécurisé
                </li>
                <li>
                    <i data-lucide="truck" aria-hidden="true"></i>
                    Livraison rapide
                </li>
                <li>
                    <i data-lucide="headphones" aria-hidden="true"></i>
                    Support client
                </li>
            </ul>
            <span class="footer-legal-placeholder">Mentions légales</span>
        </div>
    </div>
</footer>

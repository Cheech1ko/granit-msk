(function () {
    'use strict';

    var CONFIG = {
        siteName:  'МосПамять.рф',
        phone:     '+79362200900',
        phoneDisplay: '+7 (936) 2200-900',
        tgLink:    'https://t.me/ваш_телеграм',
        waLink:    'https://wa.me/79362200900',
        maxLink:   'https://ваша-ссылка-на-max',
    };

    function getBasePath() {
        var path = window.location.pathname;
        if (path.includes('/catalog/') || path.includes('/product/')) {
            return '../';
        }
        return '';
    }

    function img(name) {
        return getBasePath() + 'img/' + name;
    }

    function getPrefix() {
        var path = window.location.pathname;
        if (path.includes('/catalog/') || path.includes('/product/')) {
            return '../';
        }
        return '';
    }

    function isActive(href) {
        var path = window.location.pathname;
        var prefix = getPrefix();
        if (href === prefix + 'index.html' || href === prefix) {
            return path === '/' || path.endsWith('/index.html');
        }
        return path.includes(href.replace(prefix, ''));
    }

    function navLink(href, label, extra) {
        var cls = 'nav-link' + (isActive(href) ? ' active' : '');
        return '<a href="' + href + '" class="' + cls + '"' + (extra || '') + '>' + label + '</a>';
    }

    function socialIcons(size) {
        size = size || 24;
        return [
            { href: CONFIG.tgLink,  src: img('telegram.svg'), alt: 'Telegram',  title: 'Написать в Telegram'  },
            { href: CONFIG.waLink,  src: img('whatsapp.svg'), alt: 'WhatsApp',  title: 'Написать в WhatsApp'  },
            { href: CONFIG.maxLink, src: img('max.svg'),      alt: 'Max',       title: 'Написать в Max'       },
        ].map(function(s) {
            return '<a href="' + s.href + '" target="_blank" rel="noopener noreferrer" ' +
                   'class="social-icon" title="' + s.title + '" aria-label="' + s.alt + '">' +
                   '<img src="' + s.src + '" alt="' + s.alt + '" width="' + size + '" height="' + size + '" loading="lazy">' +
                   '</a>';
        }).join('');
    }

    function renderHeader() {
        var el = document.getElementById('site-header');
        if (!el) return;

        var isProductPage = window.location.pathname.includes('/product/');
        var ctaOnclick = isProductPage ? 'openOrderModal()' : 'openModal()';
        var ctaText = 'Оставить заявку';
        var prefix = getPrefix();

        var categories = [
            ['vertical', 'Вертикальные'],
            ['horizontal', 'Горизонтальные'],
            ['carved', 'Резные'],
            ['double', 'Двойные'],
            ['combined', 'Комбинированные'],
            ['cross', 'С крестом'],
            ['complex', 'Комплексы'],
        ];

        var catalogDropdownHtml = categories.map(function(c) {
            return '<a href="' + prefix + 'catalog/?category=' + c[0] + '">' + c[1] + '</a>';
        }).join('');

        el.innerHTML =
            '<div class="header-inner">' +
              '<a href="' + prefix + 'index.html" class="logo">' +
                '<div class="logo-icon">' +
                  '<img src="' + img('моспамятьрф-04.svg') + '" alt="МосПамять.рф" ' +
                       'style="width:100%;height:100%;object-fit:contain;display:block;" ' +
                       'onerror="this.style.display=\'none\'; this.parentElement.textContent=\'МП\'">' +
                '</div>' +
                '<div class="logo-text">' +
                  '<div class="logo-name">' + CONFIG.siteName + '</div>' +
                '</div>' +
              '</a>' +

              '<nav class="desktop-nav">' +
                navLink(prefix + 'index.html#about',   'О компании') +
                navLink(prefix + 'catalog/',            'Каталог') +
                navLink(prefix + 'index.html#faq',      'FAQ') +
                navLink(prefix + 'index.html#gallery',  'Работы') +
                navLink(prefix + 'index.html#contact',  'Контакты') +
              '</nav>' +

              '<div class="social-icons">' + socialIcons(22) + '</div>' +

              '<button class="header-cta desktop-cta" onclick="' + ctaOnclick + '" style="border:none;cursor:pointer;background:var(--gold);font-weight:700;font-size:14px;padding:10px 20px;border-radius:var(--radius);font-family:\'Montserrat\',sans-serif;color:var(--dark);transition:background .2s;">' +
                ctaText +
              '</button>' +
            '</div>' +

            /* ===== МОБИЛЬНОЕ НИЖНЕЕ МЕНЮ ===== */
            '<div class="mobile-bottom-nav" id="mobileBottomNav">' +
              /* Кнопка "Каталог" */
              '<div class="mobile-nav-item" id="catalogToggle" onclick="toggleCatalogDropdown()">' +
                '<i class="fas fa-th-list"></i>' +
                '<span>Каталог</span>' +
              '</div>' +

              /* Логотип между кнопками */
              '<div class="mobile-logo-divider">' +
                '<img src="' + img('моспамятьрф-04.svg') + '" alt="МосПамять" width="80" height="40" loading="lazy" ' +
                     'onerror="this.style.display=\'none\'">' +
              '</div>' +

              /* Кнопка "Меню" */
              '<div class="mobile-nav-item" id="menuToggle" onclick="toggleMenu()">' +
                '<i class="fas fa-bars"></i>' +
                '<span>Меню</span>' +
              '</div>' +

              /* ВЫПАДАЮЩЕЕ МЕНЮ "КАТАЛОГ" (снизу вверх) */
              '<div class="mobile-dropdown catalog-dropdown" id="catalogDropdown">' +
                '<div class="dropdown-header">Категории</div>' +
                '<a href="' + prefix + 'catalog/" class="dropdown-all">Все товары</a>' +
                catalogDropdownHtml +
              '</div>' +

              /* ВЫПАДАЮЩЕЕ МЕНЮ "МЕНЮ" (снизу вверх) */
              '<nav class="mobile-dropdown menu-dropdown" id="menuDropdown">' +
                '<div class="dropdown-header">Меню</div>' +
                '<a href="' + prefix + 'index.html#about"   onclick="closeAllDropdowns()">О компании</a>' +
                '<a href="' + prefix + 'catalog/"            onclick="closeAllDropdowns()">Каталог</a>' +
                '<a href="' + prefix + 'index.html#faq"     onclick="closeAllDropdowns()">FAQ</a>' +
                '<a href="' + prefix + 'index.html#gallery" onclick="closeAllDropdowns()">Галерея работ</a>' +
                '<a href="' + prefix + 'index.html#contact" onclick="closeAllDropdowns()">Контакты</a>' +

                '<div class="dropdown-divider"></div>' +

                '<div class="dropdown-social">' +
                  '<a href="' + CONFIG.tgLink + '" target="_blank" rel="noopener noreferrer">' +
                    '<img src="' + img('telegram.svg') + '" width="20" height="20" alt="Telegram"> Telegram</a>' +
                  '<a href="' + CONFIG.waLink + '" target="_blank" rel="noopener noreferrer">' +
                    '<img src="' + img('whatsapp.svg') + '" width="20" height="20" alt="WhatsApp"> WhatsApp</a>' +
                  '<a href="' + CONFIG.maxLink + '" target="_blank" rel="noopener noreferrer">' +
                    '<img src="' + img('max.svg') + '" width="20" height="20" alt="Max"> Max</a>' +
                '</div>' +

                '<a href="tel:' + CONFIG.phone + '" class="dropdown-phone">' +
                  '<i class="fas fa-phone"></i> ' + CONFIG.phoneDisplay +
                '</a>' +

                '<button class="dropdown-cta" onclick="' + ctaOnclick + '; closeAllDropdowns();">' +
                  '<i class="fas fa-pen"></i> ' + ctaText +
                '</button>' +
              '</nav>' +
            '</div>';
    }

    // ===== РЕНДЕР ПОДВАЛА =====
    function renderFooter() {
        var el = document.getElementById('site-footer');
        if (!el) return;

        var prefix = getPrefix();
        var cats = [
            ['vertical',   'Вертикальные'],
            ['horizontal', 'Горизонтальные'],
            ['carved',     'Резные'],
            ['double',     'Двойные'],
            ['combined',   'Комбинированные'],
            ['cross',      'С крестом'],
            ['complex',    'Комплексы'],
        ];

        el.innerHTML =
            '<div class="footer-inner">' +
              '<div class="footer-brand">' +
                '<div class="logo" style="margin-bottom:14px">' +
                  '<div class="logo-icon">' +
                    '<img src="' + img('моспамятьрф-04.svg') + '" alt="МосПамять.рф" ' +
                         'style="width:100%;height:100%;object-fit:contain;display:block;" ' +
                         'onerror="this.style.display=\'none\'">' +
                  '</div>' +
                  '<div class="logo-text"><div class="logo-name">' + CONFIG.siteName + '</div></div>' +
                '</div>' +
                '<p>Проектирование, изготовление и установка памятников в Москве и МО с 2009 года.</p>' +
                '<div class="footer-social">' + '</div>' +
              '</div>' +

              '<div class="footer-col">' +
                '<h4>Каталог</h4><ul>' +
                cats.map(function(c) {
                    return '<li><a href="' + prefix + 'catalog/?category=' + c[0] + '">' + c[1] + '</a></li>';
                }).join('') +
                '</ul>' +
              '</div>' +

              '<div class="footer-col">' +
                '<h4>Контакты</h4>' +
                '<ul>' +
                  '<li><a href="tel:' + CONFIG.phone + '">' + CONFIG.phoneDisplay + '</a></li>' +
                  '<li><a href="#" onclick="event.preventDefault();openEmailModal&&openEmailModal()">info@mospamyat.ru</a></li>' +
                '</ul>' +
                '<h4 style="margin-top:16px">Мессенджеры</h4>' +
                '<div class="footer-social">' + socialIcons(30) + '</div>' +
              '</div>' +
            '</div>' +

            '<div class="footer-bottom">' +
              '<span>' + CONFIG.siteName + ' © 2009–' + new Date().getFullYear() + '</span>' +
              '<a href="' + prefix + 'privacy-policy.html" style="color:rgba(255,255,255,0.4);font-size:12px;">' +
                'Политика конфиденциальности</a>' +
            '</div>';
    }

    // ===== ФУНКЦИИ ДЛЯ МЕНЮ =====
    window.toggleCatalogDropdown = function () {
        var dropdown = document.getElementById('catalogDropdown');
        var menuDropdown = document.getElementById('menuDropdown');
        if (!dropdown) return;

        if (menuDropdown && menuDropdown.classList.contains('open')) {
            menuDropdown.classList.remove('open');
        }

        dropdown.classList.toggle('open');
        document.body.style.overflow = dropdown.classList.contains('open') ? 'hidden' : '';
    };

    window.toggleMenu = function () {
        var menuDropdown = document.getElementById('menuDropdown');
        var catalogDropdown = document.getElementById('catalogDropdown');
        if (!menuDropdown) return;

        if (catalogDropdown && catalogDropdown.classList.contains('open')) {
            catalogDropdown.classList.remove('open');
        }

        menuDropdown.classList.toggle('open');
        document.body.style.overflow = menuDropdown.classList.contains('open') ? 'hidden' : '';
    };

    window.closeAllDropdowns = function () {
        var catalogDropdown = document.getElementById('catalogDropdown');
        var menuDropdown = document.getElementById('menuDropdown');
        if (catalogDropdown) catalogDropdown.classList.remove('open');
        if (menuDropdown) menuDropdown.classList.remove('open');
        document.body.style.overflow = '';
    };

    // Закрываем при клике вне
    document.addEventListener('click', function (e) {
        var catalogDropdown = document.getElementById('catalogDropdown');
        var menuDropdown = document.getElementById('menuDropdown');
        var catalogBtn = document.getElementById('catalogToggle');
        var menuBtn = document.getElementById('menuToggle');

        if (catalogDropdown && catalogDropdown.classList.contains('open')) {
            if (!catalogDropdown.contains(e.target) && (!catalogBtn || !catalogBtn.contains(e.target))) {
                catalogDropdown.classList.remove('open');
                document.body.style.overflow = '';
            }
        }

        if (menuDropdown && menuDropdown.classList.contains('open')) {
            if (!menuDropdown.contains(e.target) && (!menuBtn || !menuBtn.contains(e.target))) {
                menuDropdown.classList.remove('open');
                document.body.style.overflow = '';
            }
        }
    });

    // Закрываем по ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllDropdowns();
        }
    });

    var css = `
/* ─── ДЕСКТОПНЫЕ СТИЛИ ─── */
.social-icons {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-left: 12px;
}
.social-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    overflow: hidden;
    transition: transform .2s, opacity .2s;
    background: rgba(255,255,255,0.06);
}
.social-icon:hover {
    background: var(--gold);
    transform: scale(1.08);
}
.social-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}
.header-cta {
    background: var(--gold);
    color: var(--dark);
    font-weight: 700;
    font-size: 14px;
    padding: 10px 20px;
    border-radius: var(--radius);
    white-space: nowrap;
    transition: background .2s;
    font-family: 'Montserrat', sans-serif;
    border: none;
    cursor: pointer;
}
.header-cta:hover {
    background: var(--gold-light);
}

.desktop-nav {
    display: flex;
    align-items: center;
    gap: 4px;
}
.desktop-nav .nav-link {
    color: rgba(255,255,255,0.8);
    font-size: 14px;
    font-weight: 600;
    padding: 8px 14px;
    border-radius: 4px;
    transition: background .2s, color .2s;
    white-space: nowrap;
    font-family: 'Montserrat', sans-serif;
    text-decoration: none;
}
.desktop-nav .nav-link:hover {
    background: rgba(255,255,255,0.08);
    color: #fff;
}
.desktop-nav .nav-link.active {
    color: var(--gold-light);
}

/* ===== ЛОГОТИП ===== */
.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: #fff;
    flex-shrink: 0;
}
.logo-icon {
    width: 100px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    overflow: hidden;
}
.logo-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}
.logo-name {
    font-size: 20px;
    font-weight: 800;
    letter-spacing: .5px;
    font-family: 'Montserrat', sans-serif;
    white-space: nowrap;
}
.logo-sub {
    font-size: 11px;
    opacity: .7;
    font-weight: 400;
}

/* ─── МОБИЛЬНОЕ НИЖНЕЕ МЕНЮ ─── */
.mobile-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 999;
    background: var(--dark);
    border-top: 1px solid rgba(255,255,255,0.08);
    justify-content: space-around;
    align-items: center;
    padding: 6px 0 env(safe-area-inset-bottom, 8px);
    box-shadow: 0 -4px 20px rgba(0,0,0,0.3);
}

.mobile-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 4px 20px;
    cursor: pointer;
    color: rgba(255,255,255,0.6);
    transition: color .2s;
    font-size: 10px;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    border: none;
    background: none;
    flex: 1;
}
.mobile-nav-item i {
    font-size: 22px;
    color: rgba(255,255,255,0.6);
    transition: color .2s;
}
.mobile-nav-item:hover {
    color: #fff;
}
.mobile-nav-item:hover i {
    color: var(--gold-light);
}
.mobile-nav-item span {
    font-size: 10px;
    letter-spacing: 0.3px;
}

.mobile-logo-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    flex-shrink: 0;
    pointer-events: none;
}
.mobile-logo-divider img {
    display: block;
    width: 80px;
    height: 40px;
    object-fit: contain;
    opacity: 0.8;
}

/* ─── ВЫПАДАЮЩИЕ МЕНЮ (снизу вверх) ─── */
.mobile-dropdown {
    display: none;
    position: fixed;
    bottom: 64px;
    left: 0;
    right: 0;
    background: var(--dark);
    padding: 0 0 20px;
    border-radius: 16px 16px 0 0;
    box-shadow: 0 -8px 30px rgba(0,0,0,0.5);
    z-index: 1000;
    flex-direction: column;
    max-height: 65vh;
    overflow-y: auto;
    animation: slideUp .3s ease;
}
.mobile-dropdown.open {
    display: flex;
}

@keyframes slideUp {
    from { transform: translateY(100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.dropdown-header {
    padding: 16px 20px 12px;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,0.4);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    font-family: 'Montserrat', sans-serif;
}

.mobile-dropdown a {
    display: block;
    color: rgba(255,255,255,0.85);
    padding: 12px 20px;
    font-size: 15px;
    font-weight: 500;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    text-decoration: none;
    transition: background .2s;
}
.mobile-dropdown a:hover {
    background: rgba(255,255,255,0.06);
}
.mobile-dropdown a:last-child {
    border-bottom: none;
}

.dropdown-all {
    font-weight: 700 !important;
    color: var(--gold-light) !important;
}

.dropdown-divider {
    height: 1px;
    background: rgba(255,255,255,0.08);
    margin: 6px 20px;
}

.dropdown-social {
    display: flex;
    gap: 16px;
    padding: 12px 20px;
    flex-wrap: wrap;
}
.dropdown-social a {
    color: rgba(255,255,255,0.7);
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    padding: 4px 0;
    border-bottom: none !important;
    transition: color .2s;
}
.dropdown-social a:hover {
    color: #fff;
}
.dropdown-social img {
    border-radius: 50%;
    flex-shrink: 0;
}

.dropdown-phone {
    color: var(--gold-light) !important;
    font-size: 17px !important;
    font-weight: 700 !important;
    padding: 12px 20px !important;
    border-top: 1px solid rgba(255,255,255,0.08);
    margin-top: 4px;
}
.dropdown-phone i {
    margin-right: 10px;
}

.dropdown-cta {
    margin: 12px 20px 4px;
    background: var(--gold);
    color: var(--dark);
    font-weight: 700;
    font-size: 15px;
    padding: 14px;
    border-radius: var(--radius);
    border: none;
    cursor: pointer;
    font-family: 'Montserrat', sans-serif;
    transition: background .2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.dropdown-cta:hover {
    background: var(--gold-light);
}

/* ─── FOOTER ─── */
.footer-social {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}

/* ===== АДАПТАЦИЯ ===== */
@media (max-width: 768px) {
    .header-inner {
        display: flex;
        align-items: center;
        justify-content: center !important;
        padding: 8px 16px;
        min-height: 56px;
        gap: 0;
    }
    .logo {
        margin: 0 auto;
        gap: 8px;
        justify-content: center;
        flex: 1;
    }
    .logo-icon {
        width: 70px;
        height: 35px;
        flex-shrink: 0;
    }
    .logo-name {
        font-size: 14px !important;
        white-space: nowrap;
    }
    .logo-text .logo-sub {
        display: none;
    }

    .desktop-nav {
        display: none !important;
    }
    .social-icons {
        display: none !important;
    }
    .desktop-cta {
        display: none !important;
    }

    .mobile-bottom-nav {
        display: flex !important;
    }

    .mobile-dropdown {
        bottom: 64px;
    }

    body {
        padding-bottom: 64px;
    }
}

@media (max-width: 480px) {
    .header-inner {
        padding: 6px 12px;
        min-height: 48px;
    }
    .logo-icon {
        width: 55px;
        height: 28px;
    }
    .logo-name {
        font-size: 12px !important;
    }
    .logo {
        gap: 6px;
    }

    .mobile-bottom-nav {
        padding: 4px 0 env(safe-area-inset-bottom, 6px);
    }
    .mobile-nav-item {
        padding: 2px 8px;
    }
    .mobile-nav-item i {
        font-size: 20px;
    }
    .mobile-nav-item span {
        font-size: 9px;
    }
    .mobile-logo-divider img {
        width: 55px;
        height: 28px;
    }
    .mobile-dropdown {
        bottom: 56px;
    }
    body {
        padding-bottom: 56px;
    }
}
    `;

    var styleEl = document.createElement('style');
    styleEl.textContent = css;
    document.head.appendChild(styleEl);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            renderHeader();
            renderFooter();
        });
    } else {
        renderHeader();
        renderFooter();
    }
})();
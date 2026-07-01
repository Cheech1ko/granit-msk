function toggleMenu() {
    var nav = document.getElementById('mobileNav');
    if (nav) nav.classList.toggle('open');
}

function openModal() {
    var overlay = document.getElementById('modalOverlay');
    if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal() {
    var overlay = document.getElementById('modalOverlay');
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
}

function closeModalOutside(e) {
    var overlay = document.getElementById('modalOverlay');
    if (e.target === overlay) closeModal();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

function openPhoneModal() {
    var overlay = document.getElementById('phoneModalOverlay');
    if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    } else {
        window.location.href = 'tel:+79362200900';
    }
}

function closePhoneModal() {
    var overlay = document.getElementById('phoneModalOverlay');
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
}

function closePhoneModalOutside(e) {
    var overlay = document.getElementById('phoneModalOverlay');
    if (e.target === overlay) closePhoneModal();
}

function openEmailModal() {
    var overlay = document.getElementById('emailModalOverlay');
    if (overlay) {
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

function closeEmailModal() {
    var overlay = document.getElementById('emailModalOverlay');
    if (overlay) {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
}

function closeEmailModalOutside(e) {
    var overlay = document.getElementById('emailModalOverlay');
    if (e.target === overlay) closeEmailModal();
}

function copyEmail() {
    var email = 'info@granvremeni.ru';
    navigator.clipboard.writeText(email).then(function() {
        var msg = document.getElementById('copyMessage');
        if (msg) {
            msg.textContent = '✅ Адрес скопирован!';
            msg.style.color = '#2e7d32';
        }
    }).catch(function() {
        var input = document.createElement('input');
        input.value = email;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        var msg = document.getElementById('copyMessage');
        if (msg) {
            msg.textContent = '✅ Адрес скопирован!';
            msg.style.color = '#2e7d32';
        }
    });
}

function openGmail() {
    window.open('https://mail.google.com/mail/?view=cm&fs=1&to=info@granvremeni.ru', '_blank');
    closeEmailModal();
}

function toggleFaq(btn) {
    var item = btn.parentElement;
    var isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item').forEach(function(i) {
        i.classList.remove('open');
    });
    if (!isOpen) item.classList.add('open');
}

function submitForm(formId, successId) {
    var formWrap = document.getElementById(formId);
    if (!formWrap) {
        console.error('Форма с id "' + formId + '" не найдена');
        return;
    }

    var isModal = formId === 'modalFormWrap';
    var name, phone, interest, comment, cemetery;

    if (isModal) {
        name = document.getElementById('mname')?.value?.trim() || '';
        phone = document.getElementById('mphone')?.value?.trim() || '';
        interest = document.getElementById('mtype')?.value || 'Не указано';
        comment = 'Заявка из модального окна';
        cemetery = document.getElementById('mcemetery')?.value?.trim() || 'Не указано';
    } else {
        name = document.getElementById('fname')?.value?.trim() || '';
        phone = document.getElementById('fphone')?.value?.trim() || '';
        interest = document.getElementById('ftype')?.value || 'Не указано';
        comment = document.getElementById('fcomment')?.value?.trim() || 'Без комментария';
        cemetery = document.getElementById('fcemetery')?.value?.trim() || 'Не указано';
    }

    var errors = [];

    if (!name) {
        errors.push('Введите ваше имя');
        var nameField = isModal ? document.getElementById('mname') : document.getElementById('fname');
        if (nameField) showError(nameField, 'Введите ваше имя');
    }

    if (!phone) {
        errors.push('Введите номер телефона');
        var phoneField = isModal ? document.getElementById('mphone') : document.getElementById('fphone');
        if (phoneField) showError(phoneField, 'Введите номер телефона');
    } else {
        var digits = phone.replace(/\D/g, '');
        if (digits.length < 10) {
            errors.push('Введите полный номер телефона');
            var phoneField2 = isModal ? document.getElementById('mphone') : document.getElementById('fphone');
            if (phoneField2) showError(phoneField2, 'Введите полный номер телефона (10-11 цифр)');
        }
    }

    if (!isModal) {
        var check = document.getElementById('fcheck');
        if (check && !check.checked) {
            errors.push('Необходимо согласие на обработку данных');
            showError(check, 'Необходимо согласие');
        }
    }

    if (errors.length > 0) {
        var firstError = formWrap.querySelector('.error-msg');
        if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    fetch('https://formspree.io/f/mojokdop', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: name,
            phone: phone,
            interest: interest,
            comment: comment + '\nКладбище: ' + cemetery,
            source: isModal ? 'Модальное окно' : 'Основная форма'
        })
    })
    .then(function(response) {
        if (response.ok) {
            var success = document.getElementById(successId);
            if (success) {
                success.style.display = 'block';
                if (isModal) {
                    document.getElementById('mname').value = '';
                    document.getElementById('mphone').value = '';
                } else {
                    document.getElementById('fname').value = '';
                    document.getElementById('fphone').value = '';
                    document.getElementById('fcomment').value = '';
                    var check2 = document.getElementById('fcheck');
                    if (check2) check2.checked = false;
                }
                setTimeout(function() {
                    success.style.display = 'none';
                    if (isModal) closeModal();
                }, 5000);
            }
        } else {
            alert('Ошибка отправки. Попробуйте позже.');
        }
    })
    .catch(function(error) {
        console.error('Ошибка:', error);
        alert('Ошибка отправки. Проверьте интернет.');
    });
}

function showError(input, message) {
    input.classList.add('error');
    input.style.borderColor = '#d32f2f';
    input.style.borderWidth = '2px';

    var oldError = input.parentNode ? input.parentNode.querySelector('.error-msg') : null;
    if (oldError) oldError.remove();

    var errorDiv = document.createElement('div');
    errorDiv.className = 'error-msg';
    errorDiv.style.cssText = 'color:#d32f2f; font-size:12px; margin-top:4px; font-weight:600;';
    errorDiv.textContent = '⚠ ' + message;

    if (input.parentNode) {
        input.parentNode.appendChild(errorDiv);
    }
}

function sendCallbackRequest() {
    var name = document.getElementById('cbname')?.value?.trim() || '';
    var phone = document.getElementById('cbphone')?.value?.trim() || '';
    var time = document.getElementById('cbtime')?.value?.trim() || 'Не указано';
    var cemetery = document.getElementById('cbcemetery')?.value?.trim() || 'Не указано';

    if (!name || !phone) {
        alert('Пожалуйста, заполните имя и телефон');
        return;
    }
    var digits = phone.replace(/\D/g, '');
    if (digits.length < 10) {
        alert('Введите полный номер телефона (10-11 цифр)');
        return;
    }

    fetch('https://formspree.io/f/mojokdop', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, phone, time, cemetery, source: 'Выезд менеджера' })
    })
    .then(function(response) {
        if (response.ok) {
            alert('Спасибо! Менеджер свяжется с вами для подтверждения выезда.');
            document.getElementById('cbname').value = '';
            document.getElementById('cbphone').value = '';
            document.getElementById('cbtime').value = '';
            document.getElementById('cbcemetery').value = '';
        } else {
            alert('Ошибка отправки. Попробуйте позже.');
        }
    })
    .catch(function(error) {
        console.error('Ошибка:', error);
        alert('Ошибка отправки. Проверьте интернет.');
    });
}

var PRODUCTS = [];

function loadProducts(callback) {
    if (PRODUCTS.length > 0) {
        if (callback) callback(PRODUCTS);
        return;
    }

    fetch('../data/products.json')
        .then(function(response) {
            if (!response.ok) {
                return fetch('data/products.json');
            }
            return response;
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Не удалось загрузить products.json');
            }
            return response.json();
        })
        .then(function(data) {
            PRODUCTS = data;
            if (callback) callback(PRODUCTS);
        })
        .catch(function(error) {
            console.error('Ошибка загрузки товаров:', error);
            PRODUCTS = [
                { id: 1, name: 'Вертикальный «Классик»', category: 'vertical', price: 18000, imageIcon: 'mountain', badge: 'Хит', popular: true, sizes: ['120×60 см'], materials: ['Гранит габбро'], description: 'Стандартная вертикальная стела.' },
                { id: 2, name: 'Вертикальный «Резной»', category: 'vertical', price: 28000, imageIcon: 'mountain', badge: null, popular: true, sizes: ['130×65 см'], materials: ['Гранит красный'], description: 'Вертикальная стела с резьбой.' },
                { id: 3, name: 'Семейный (двойной)', category: 'double', price: 36000, imageIcon: 'mountain', badge: 'Двойной', popular: true, sizes: ['120×100 см'], materials: ['Гранит габбро'], description: 'Для двоих захоронений.' },
                { id: 4, name: 'Мраморный «Классик»', category: 'marble', price: 14000, imageIcon: 'archway', badge: null, popular: true, sizes: ['100×50 см'], materials: ['Мрамор белый'], description: 'Стела из белого мрамора.' },
                { id: 5, name: 'Ограда профильная', category: 'fence', price: 12000, imageIcon: 'fence', badge: null, popular: true, sizes: ['2×1.5 м'], materials: ['Металл'], description: 'Ограда из профильной трубы.' },
                { id: 6, name: 'Цоколь гранитный', category: 'socle', price: 22000, imageIcon: 'layer-group', badge: null, popular: true, sizes: ['200×100 см'], materials: ['Гранит'], description: 'Гранитный цоколь.' },
                { id: 7, name: 'Горизонтальный «Стандарт»', category: 'horizontal', price: 16000, imageIcon: 'mountain', badge: null, popular: false, sizes: ['100×60 см'], materials: ['Гранит серый'], description: 'Горизонтальная стела.' },
                { id: 8, name: 'Горизонтальный «Резной»', category: 'horizontal', price: 24000, imageIcon: 'mountain', badge: null, popular: false, sizes: ['110×65 см'], materials: ['Гранит красный'], description: 'Горизонтальная стела с резьбой.' },
                { id: 9, name: 'Двойной «Резной»', category: 'double', price: 45000, imageIcon: 'mountain', badge: 'Двойной', popular: false, sizes: ['140×120 см'], materials: ['Гранит красный'], description: 'Двойной памятник с резьбой.' },
                { id: 10, name: 'Мраморный «Резной»', category: 'marble', price: 22000, imageIcon: 'archway', badge: null, popular: false, sizes: ['110×55 см'], materials: ['Мрамор белый'], description: 'Мраморная стела с резьбой.' }
            ];
            if (callback) callback(PRODUCTS);
        });
}

function renderPopularProducts() {
    var grid = document.getElementById('popularGrid');
    if (!grid) return;

    loadProducts(function(products) {
        var popular = products.filter(function(p) { return p.popular === true; }).slice(0, 6);

        if (popular.length === 0) {
            grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--text-light);">Нет популярных товаров</p>';
            return;
        }

        grid.innerHTML = popular.map(function(p) {
            return `
                <div class="item-card" data-cat="${p.category}">
                    <div class="item-img">
                        <i class="fas fa-${p.imageIcon || 'mountain'}"></i>
                        ${p.badge ? `<span class="item-badge">${p.badge}</span>` : ''}
                    </div>
                    <div class="item-body">
                        <h3>${p.name}</h3>
                        <div class="item-dims">
                            <span class="dim-tag">${p.sizes[0].label}</span>
                            <span class="dim-tag">${p.materials[0].label}</span>
                        </div>
                        <p class="item-desc">${p.description}</p>
                        <div class="item-footer" style="flex-direction:column;gap:8px;align-items:stretch;">
                            <div class="item-price">
                                <div class="item-price-from">цена от</div>
                                <div class="item-price-val">${p.price.toLocaleString('ru-RU')} <span class="item-price-unit">₽</span></div>
                            </div>
                            <div class="item-actions">
                                <a href="product/?id=${p.id}" class="btn-card">
                                    <i class="fas fa-calculator"></i> Рассчитать
                                </a>
                                <a href="https://t.me/ваш_телеграм" target="_blank" class="btn-card btn-telegram">
                                    <i class="fab fa-telegram-plane"></i> Написать
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    });
}

var currentCategory = 'all';
var currentPage = 1;
var ITEMS_PER_PAGE = 20;
var allProducts = [];

function initCatalog() {
    var grid = document.getElementById('productGrid');
    var count = document.getElementById('productCount');
    var pagination = document.getElementById('pagination');

    if (!grid || !count || !pagination) return;

    loadProducts(function(products) {
        allProducts = products;
        renderCatalog();
    });
}

function renderCatalog() {
    var grid = document.getElementById('productGrid');
    var count = document.getElementById('productCount');
    var pagination = document.getElementById('pagination');

    if (!grid) return;

    var filtered = allProducts;
    if (currentCategory !== 'all') {
        filtered = allProducts.filter(function(p) { return p.category === currentCategory; });
    }

    count.textContent = 'Найдено ' + filtered.length + ' товаров';

    var totalPages = Math.ceil(filtered.length / ITEMS_PER_PAGE);
    if (currentPage > totalPages) currentPage = 1;
    var start = (currentPage - 1) * ITEMS_PER_PAGE;
    var paginated = filtered.slice(start, start + ITEMS_PER_PAGE);

    if (paginated.length === 0) {
        grid.innerHTML = '<div class="catalog-empty">В этой категории пока нет товаров</div>';
    } else {
        grid.innerHTML = paginated.map(function(p) {
            return `
                <div class="catalog-item" onclick="window.location.href='../product/?id=${p.id}'">
                    <div class="catalog-item-img">
                        <i class="fas fa-${p.imageIcon || 'mountain'}"></i>
                    </div>
                    <div class="catalog-item-body">
                        <h3>${p.name}</h3>
                        <div class="dims">${p.sizes[0].label}</div>
                        <div class="price">${p.price.toLocaleString('ru-RU')} <span>₽</span></div>
                        <div class="item-actions">
                            <a href="../product/?id=${p.id}" class="btn-card">
                                <i class="fas fa-calculator"></i> Рассчитать
                            </a>
                            <a href="https://t.me/ваш_телеграм" target="_blank" class="btn-card btn-telegram">
                                <i class="fab fa-telegram-plane"></i> Написать
                            </a>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    var pagHtml = '';
    for (var i = 1; i <= totalPages; i++) {
        pagHtml += '<button class="' + (i === currentPage ? 'active' : '') + '" onclick="goToPage(' + i + ')">' + i + '</button>';
    }
    pagination.innerHTML = pagHtml;
}

function goToPage(page) {
    currentPage = page;
    renderCatalog();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function setCategory(category) {
    currentCategory = category;
    currentPage = 1;
    document.querySelectorAll('.filter-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.category === category);
    });
    renderCatalog();
    var url = new URL(window.location);
    if (category === 'all') {
        url.searchParams.delete('category');
    } else {
        url.searchParams.set('category', category);
    }
    window.history.pushState({}, '', url);
}

function loadProduct() {
    var container = document.getElementById('productContainer');
    if (!container) return;

    var params = new URLSearchParams(window.location.search);
    var id = parseInt(params.get('id'));

    loadProducts(function(products) {
        var product = products.find(function(p) { return p.id === id; });

        if (!product) {
            container.innerHTML = `
                <div class="product-error" style="grid-column:1/-1;text-align:center;padding:60px 20px;">
                    <h2>Товар не найден</h2>
                    <p><a href="../catalog/" style="color:var(--gold);">Вернуться в каталог</a></p>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <a href="../catalog/" class="back-link" style="grid-column:1/-1;display:inline-block;margin-bottom:20px;color:var(--text-light);font-size:14px;">
                <i class="fas fa-arrow-left"></i> Назад в каталог
            </a>
            <div class="product-image">
                <i class="fas fa-${product.imageIcon || 'mountain'}"></i>
            </div>
            <div class="product-info">
                <h1>${product.name}</h1>
                <div class="product-price">${product.price.toLocaleString('ru-RU')} <span>₽</span></div>
                <p class="product-desc">${product.description}</p>
                <div class="product-specs">
                    <h3>Доступные размеры</h3>
                    <ul>${product.sizes.map(function(s) { return '<li>' + s + '</li>'; }).join('')}</ul>
                </div>
                <div class="product-specs">
                    <h3>Материалы</h3>
                    <ul>${product.materials.map(function(m) { return '<li>' + m + '</li>'; }).join('')}</ul>
                </div>
                <div class="product-actions">
                    <button class="btn-primary" onclick="window.location.href='../index.html#contact'">
                        <i class="fas fa-shopping-cart"></i> Заказать
                    </button>
                    <button class="btn-outline-dark" onclick="window.location.href='tel:+78412000000'">
                        <i class="fas fa-phone"></i> Позвонить
                    </button>
                </div>
            </div>
        `;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('popularGrid')) {
        renderPopularProducts();
    }

    if (document.getElementById('productGrid')) {
        document.querySelectorAll('.filter-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                setCategory(this.dataset.category);
            });
        });

        var params = new URLSearchParams(window.location.search);
        var catFromUrl = params.get('category');
        if (catFromUrl) {
            currentCategory = catFromUrl;
            document.querySelectorAll('.filter-btn').forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.category === catFromUrl);
            });
        }

        initCatalog();
    }

    if (document.getElementById('productContainer')) {
        loadProduct();
    }
});
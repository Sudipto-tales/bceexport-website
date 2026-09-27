(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var loadMoreBtn = document.getElementById('loadMoreBtn');
        var grid = document.getElementById('productsGrid');

        if (!loadMoreBtn || !grid) return;

        var category = loadMoreBtn.getAttribute('data-category');
        var currentPage = parseInt(loadMoreBtn.getAttribute('data-page') || '1', 10);
        var totalPages = parseInt(loadMoreBtn.getAttribute('data-total-pages') || '1', 10);
        var waNumber = grid.getAttribute('data-wa') || '918900379037';
        var showingCount = document.getElementById('showingCount');

        loadMoreBtn.addEventListener('click', async function () {
            if (currentPage >= totalPages) return;

            var nextPage = currentPage + 1;
            var textSpan = loadMoreBtn.querySelector('.btn-text');
            var spinnerSpan = loadMoreBtn.querySelector('.spinner-border');

            if (textSpan) textSpan.textContent = 'Loading...';
            if (spinnerSpan) spinnerSpan.classList.remove('d-none');
            loadMoreBtn.disabled = true;

            try {
                var response = await fetch('/api/public/products?categoryId=' + encodeURIComponent(category) + '&page=' + nextPage + '&perPage=12');
                var res = await response.json();

                var products = Array.isArray(res) ? res : (res.data || []);
                if (products && products.length > 0) {
                    var fragment = document.createDocumentFragment();

                    products.forEach(function (product) {
                        var col = document.createElement('div');
                        col.className = 'col-lg-3 col-md-6 product-item-col wow fadeInUp';
                        col.setAttribute('data-wow-delay', '0.1s');

                        var pImg = product.imageUrl || '/img/placeholder.png';
                        var waText = encodeURIComponent('Hi, I am interested in ' + product.name);

                        col.innerHTML = `
                            <div class="team-item p-4 h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="overflow-hidden mb-4 position-relative" style="height:240px; background:#f8f9fa; border-radius: 4px;">
                                        <img class="img-fluid w-100 h-100" style="object-fit:cover" src="${pImg}" alt="${escapeHtml(product.name)}" loading="lazy" decoding="async">
                                    </div>
                                    <h5 class="mb-1 text-truncate" title="${escapeHtml(product.name)}">${escapeHtml(product.name)}</h5>
                                    <p class="text-muted small mb-3">Product Item</p>
                                </div>
                                <div class="btn-slide mt-2">
                                    <i class="fa fa-share"></i>
                                    <span>
                                        <a href="https://wa.me/${waNumber}?text=${waText}" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i></a>
                                        <a href="https://www.facebook.com/Bceexport" target="_blank" rel="noopener"><i class="fab fa-facebook-f"></i></a>
                                        <a href="https://www.instagram.com/bceexport/" target="_blank" rel="noopener"><i class="fab fa-instagram"></i></a>
                                    </span>
                                </div>
                            </div>
                        `;

                        fragment.appendChild(col);
                    });

                    grid.appendChild(fragment);

                    currentPage = nextPage;
                    loadMoreBtn.setAttribute('data-page', currentPage);

                    if (showingCount) {
                        var currentDisplayed = grid.querySelectorAll('.product-item-col').length;
                        showingCount.textContent = currentDisplayed;
                    }

                    if (currentPage >= totalPages) {
                        loadMoreBtn.parentElement.style.display = 'none';
                    } else {
                        if (textSpan) textSpan.textContent = 'Load More Products';
                        if (spinnerSpan) spinnerSpan.classList.add('d-none');
                        loadMoreBtn.disabled = false;
                    }

                    if (window.WOW) {
                        new window.WOW().init();
                    }
                } else {
                    if (textSpan) textSpan.textContent = 'No More Products';
                    if (spinnerSpan) spinnerSpan.classList.add('d-none');
                }
            } catch (err) {
                console.error('Failed to load products:', err);
                if (textSpan) textSpan.textContent = 'Try Again';
                if (spinnerSpan) spinnerSpan.classList.add('d-none');
                loadMoreBtn.disabled = false;
            }
        });

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    });
})();

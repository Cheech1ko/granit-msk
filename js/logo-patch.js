
(function() {
    'use strict';

    var LOGO_SRC = '/img/моспамятьрф-04.svg';

    function patchLogos() {
        document.querySelectorAll('.logo-icon').forEach(function(el) {
            if (el.querySelector('img')) return;

            el.textContent = '';

            var img = document.createElement('img');
            img.src   = LOGO_SRC;
            img.alt   = 'ГраньВремени.рф';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;border-radius:inherit;';
            img.onerror = function() { this.style.display = 'none'; };
            el.appendChild(img);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', patchLogos);
    } else {
        patchLogos();
    }
})();

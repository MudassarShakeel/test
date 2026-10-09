(function () {
    'use strict';
    if (window.__mcsLang || !window.mcsLangConfig) return;
    window.__mcsLang = true;

    var SITE_LANG = String(mcsLangConfig.siteLang || 'en');
    var LANGS = String(mcsLangConfig.langs || '');
    var ALLOWED = LANGS ? LANGS.split(',') : [];
    if (ALLOWED.indexOf(SITE_LANG) === -1) ALLOWED.push(SITE_LANG);

    function getCookie(n) {
        var m = document.cookie.match('(^|;)\\s*' + n + '\\s*=\\s*([^;]+)');
        if (!m) return '';
        try { return decodeURIComponent(m[2]); } catch (e) { return ''; }
    }

    function currentLang() {
        var c = getCookie('googtrans');
        var lang = c ? (c.split('/')[2] || SITE_LANG) : SITE_LANG;
        return ALLOWED.indexOf(lang) !== -1 ? lang : SITE_LANG;
    }

    function setLang(lang) {
        if (ALLOWED.indexOf(lang) === -1) return;
        var h = location.hostname;
        var flags = '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
        var past = 'expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
        if (lang === SITE_LANG) {
            document.cookie = 'googtrans=;' + past;
            document.cookie = 'googtrans=;' + past + ' domain=' + h + ';';
            document.cookie = 'googtrans=;' + past + ' domain=.' + h + ';';
        } else {
            var v = encodeURIComponent('/' + SITE_LANG + '/' + lang).replace(/%2F/g, '/');
            document.cookie = 'googtrans=' + v + '; path=/' + flags;
            document.cookie = 'googtrans=' + v + '; path=/; domain=' + h + flags;
            document.cookie = 'googtrans=' + v + '; path=/; domain=.' + h + flags;
        }
        location.reload();
    }

    function updateUI() {
        var lang = currentLang();
        document.querySelectorAll('.mcs-lang').forEach(function (box) {
            box.querySelectorAll('.mcs-lang-menu .mcs-menu-item').forEach(function (b) {
                var on = b.getAttribute('data-lang') === lang;
                b.classList.toggle('active', on);
                if (on) {
                    var flag = box.querySelector('.mcs-lang-btn .mcs-flag');
                    var code = box.querySelector('.mcs-code');
                    var src = b.getAttribute('data-flag');
                    if (flag && /^https:\/\//.test(src || '')) flag.src = src;
                    if (code) code.textContent = lang.toUpperCase();
                }
            });
        });
    }

    function closeAll(except) {
        document.querySelectorAll('.mcs-lang.open').forEach(function (b) {
            if (b !== except) {
                b.classList.remove('open');
                var btn = b.querySelector('.mcs-lang-btn');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.mcs-lang-btn');
        var item = e.target.closest('.mcs-lang-menu .mcs-menu-item');
        if (toggle) {
            var box = toggle.parentNode;
            closeAll(box);
            var open = box.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        } else if (item) {
            var lang = item.getAttribute('data-lang');
            closeAll();
            if (lang !== currentLang()) setLang(lang);
        } else {
            closeAll();
        }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', updateUI);
    else updateUI();
    window.addEventListener('load', updateUI);

    window.mcsGoogleInit = function () {
        new google.translate.TranslateElement({
            pageLanguage: SITE_LANG,
            includedLanguages: ALLOWED.join(','),
            autoDisplay: false
        }, 'mcs_gt');
    };
    var s = document.createElement('script');
    s.src = 'https://translate.google.com/translate_a/element.js?cb=mcsGoogleInit';
    s.async = true;
    document.head.appendChild(s);
})();

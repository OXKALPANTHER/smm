(function () {
    'use strict';

    var page = document.body;
    if (!page || !page.classList.contains('landing-page')) return;

    var motionPreference = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');
    if ((motionPreference && motionPreference.matches) || !('IntersectionObserver' in window)) return;

    var sections = document.querySelectorAll('.landing-page main > section, .landing-page main > footer.footer');
    if (!sections.length) return;

    var revealTargets = [];
    var itemSelector = '.hero-copy > *, .section-head, .platform, .feature, .step, .global-note, .price-card, .trust-grid article, .payments, .faq details, .cta';

    Array.prototype.forEach.call(sections, function (section) {
        section.classList.add('landing-section-reveal');
        revealTargets.push(section);

        var items = section.querySelectorAll(itemSelector);
        Array.prototype.forEach.call(items, function (item, index) {
            item.classList.add('landing-reveal-item');
            item.style.setProperty('--landing-item-delay', Math.min(index * 70, 280) + 'ms');
            revealTargets.push(item);
        });
    });

    page.classList.add('landing-reveal-ready');

    var observer = new IntersectionObserver(function (entries, currentObserver) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('landing-is-visible');
            currentObserver.unobserve(entry.target);
        });
    }, {
        threshold: 0.08,
        rootMargin: '0px 0px -40px 0px'
    });

    revealTargets.forEach(function (target) {
        observer.observe(target);
    });
})();

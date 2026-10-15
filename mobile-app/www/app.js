document.addEventListener('DOMContentLoaded', () => {
    const splash = document.getElementById('splash');
    const url = 'https://royal.t20tech.site/login.php?source=app';

    window.location.replace(url);
    setTimeout(() => splash.classList.add('hidden'), 800);
});

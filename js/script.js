(function () {
  const toggle = document.querySelector('.menu__button');
  const menu = document.querySelector('.nav__menu');
  const header = document.querySelector('header.nav');
  const links = menu ? Array.from(menu.querySelectorAll('a[href^="#"]')) : [];

  const closeMenu = () => {
    if (!toggle || !menu) return;
    document.body.classList.remove('menu-open');
    menu.classList.remove('is-open');
    toggle.classList.remove('is-active');
    toggle.setAttribute('aria-expanded', 'false');
  };

  const openMenu = () => {
    if (!toggle || !menu) return;
    document.body.classList.add('menu-open');
    menu.classList.add('is-open');
    toggle.classList.add('is-active');
    toggle.setAttribute('aria-expanded', 'true');
  };

  if (toggle && menu) {
    toggle.addEventListener('click', (e) => {
      e.preventDefault();
      if (menu.classList.contains('is-open')) {
        closeMenu();
      } else {
        openMenu();
      }
    });
  }

})();

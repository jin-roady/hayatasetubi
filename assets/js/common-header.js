document.addEventListener('DOMContentLoaded', () => {
    const headerContainer = document.createElement('div');
    headerContainer.id = 'header-placeholder';
    document.body.prepend(headerContainer);
  
    fetch('header.html')
      .then(res => res.text())
      .then(html => {
        headerContainer.innerHTML = html;
  
        // ナビ開閉動作
        const menu = document.getElementById('site-menu');
        if (!menu) return;
        menu.querySelectorAll('.nav__panel .nav__link').forEach(a => {
          a.addEventListener('click', () => { menu.open = false; });
        });
        menu.querySelector('.nav__backdrop')?.addEventListener('click', () => { menu.open = false; });
        menu.querySelector('.nav__close')?.addEventListener('click', () => { menu.open = false; });
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && menu.open) menu.open = false;
        });
      })
      .catch(err => console.error('ヘッダーの読み込みに失敗しました:', err));
  });
  
(function() {
  const key = 'theme';
  const root = document.documentElement;
  const icon = () => document.querySelector('#theme-icon');

  const getPreferred = () => localStorage.getItem(key)
    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

  const setTheme = t => {
    root.setAttribute('data-bs-theme', t);
    if (icon()) icon().className = t === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    localStorage.setItem(key, t);
  };

  setTheme(getPreferred());
  document.addEventListener('click', e => {
    if (e.target.closest('#theme-toggle')) {
      setTheme(root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
    }
  });
})();
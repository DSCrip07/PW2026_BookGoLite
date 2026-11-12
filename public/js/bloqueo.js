// Cuenta regresiva del bloqueo de login. Archivo externo para cumplir la CSP (sin scripts en línea).
(function () {
  var el = document.getElementById('cuenta-regresiva');
  var btn = document.getElementById('btn-entrar');
  if (!el) { return; }
  var s = parseInt(el.getAttribute('data-segundos'), 10) || 0;
  function fmt(n) {
    var m = Math.floor(n / 60), r = n % 60;
    return (m < 10 ? '0' : '') + m + ':' + (r < 10 ? '0' : '') + r;
  }
  function tick() {
    if (s <= 0) {
      el.parentNode.className = 'alert alert-exito';
      el.parentNode.textContent = 'Ya puede intentar de nuevo con ese correo.';
      if (btn) { btn.disabled = false; }
      return;
    }
    el.textContent = fmt(s);
    s--;
    setTimeout(tick, 1000);
  }
  tick();
})();

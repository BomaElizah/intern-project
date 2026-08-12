document.addEventListener('DOMContentLoaded', function () {
  // Fetch CSRF token and inject into all POST forms
  fetch('/csrf_token.php', { credentials: 'same-origin' })
    .then(function (res) { return res.json(); })
    .then(function (data) {
      if (!data || !data.csrf_token) return;
      var token = data.csrf_token;
      var forms = document.querySelectorAll('form[method="POST"]');
      forms.forEach(function (form) {
        if (form.querySelector('input[name="csrf_token"]')) return; // already present
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = token;
        form.appendChild(input);
      });
    })
    .catch(function (err) { console && console.warn && console.warn('CSRF token fetch failed', err); });
});

/* WPU Maintenance Request System — client-side behaviour */
document.addEventListener('DOMContentLoaded', function () {
  // Auto-dismiss flash alerts after a few seconds.
  document.querySelectorAll('.alert[data-autohide]').forEach(function (el) {
    setTimeout(function () { el.style.display = 'none'; }, 6000);
  });

  // Building -> Room dependent dropdown (Sections 6.2-6.4).
  var buildingSelect = document.getElementById('building_id');
  var roomSelect = document.getElementById('room_id');
  if (buildingSelect && roomSelect) {
    buildingSelect.addEventListener('change', function () {
      var buildingId = this.value;
      roomSelect.innerHTML = '<option value="">Loading...</option>';
      if (!buildingId) {
        roomSelect.innerHTML = '<option value="">— Select building first —</option>';
        return;
      }
      fetch(window.APP_BASE_URL + '/requests/ajax_get_rooms.php?building_id=' + encodeURIComponent(buildingId))
        .then(function (r) { return r.json(); })
        .then(function (rooms) {
          var opts = '<option value="">— No specific room —</option>';
          rooms.forEach(function (rm) {
            opts += '<option value="' + rm.room_id + '">' + rm.room_number + (rm.room_type ? ' (' + rm.room_type + ')' : '') + '</option>';
          });
          roomSelect.innerHTML = opts;
        })
        .catch(function () {
          roomSelect.innerHTML = '<option value="">— No specific room —</option>';
        });
    });
  }

  // Simple confirm() gate for destructive/administrative actions.
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // Collapse the main navigation on phone and tablet layouts.
  bindMainMenu();
  bindDashboardMenus();

  // File input preview count.
  document.querySelectorAll('input[type=file][data-preview-label]').forEach(function (input) {
    input.addEventListener('change', function () {
      var label = document.getElementById(input.getAttribute('data-preview-label'));
      if (label) {
        label.textContent = input.files.length
          ? input.files.length + ' file(s) selected'
          : '';
      }
    });
  });
});

function bindMainMenu() {
  var menuToggle = document.querySelector('.menu-toggle');
  var mainMenu = document.getElementById('main-menu');
  if (menuToggle && mainMenu && !menuToggle.dataset.bound) {
    menuToggle.dataset.bound = 'true';
    menuToggle.addEventListener('click', function () {
      var isOpen = mainMenu.classList.toggle('menu-open');
      menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }
}

// The script is loaded at the end of the page, so initialize even if the
// DOMContentLoaded event has already fired (for cached browser navigations).
bindMainMenu();
bindDashboardMenus();

function bindDashboardMenus() {
  document.querySelectorAll('.requester-rail').forEach(function (rail) {
    var navigation = rail.querySelector('.requester-rail-nav');
    if (!navigation || rail.querySelector('.dashboard-menu-toggle')) return;
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'dashboard-menu-toggle';
    toggle.setAttribute('aria-label', 'Dashboard menu');
    toggle.setAttribute('title', 'Dashboard menu');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '&#9776;';
    rail.insertBefore(toggle, navigation);
    toggle.addEventListener('click', function () {
      var isOpen = navigation.classList.toggle('menu-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  });
}

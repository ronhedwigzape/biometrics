<!-- header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Biometrics Daily Logs Sorting System</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <style>
    body { padding-top: 40px; }
  </style>
  <script>
    window.bindFlashCloseHandlers = function (scope) {
      var root = scope || document;
      root.querySelectorAll('.app-flash-close').forEach(function (button) {
        button.addEventListener('click', function () {
          var flash = button.closest('.app-flash');
          if (flash) {
            flash.classList.add('is-hiding');
            window.setTimeout(function () {
              flash.remove();
            }, 180);
          }
        });
      });
    };

    window.setAppFlash = function (html) {
      var container = document.getElementById('appFlashContainer');
      if (!container) {
        return;
      }
      container.innerHTML = html || '';
      window.bindFlashCloseHandlers(container);
    };

    window.buildAppFlash = function (type, title, message) {
      return '<div class="app-flash app-flash-' + type + '" role="status">'
        + '<strong>' + title + '</strong>'
        + '<span>' + message.replace(/\n/g, '<br>') + '</span>'
        + '<button type="button" class="app-flash-close" aria-label="Dismiss">&times;</button>'
        + '</div>';
    };

    window.handleAjaxFailure = function (message) {
      window.setAppFlash(window.buildAppFlash('danger', 'Request failed', message));
    };

    window.lockPageScroll = function () {
      var scrollY = window.scrollY || window.pageYOffset || 0;
      document.body.dataset.scrollY = String(scrollY);
      document.body.style.setProperty('--app-scroll-top', '-' + scrollY + 'px');
      document.body.classList.add('app-modal-open');
    };

    window.unlockPageScroll = function () {
      var scrollY = parseInt(document.body.dataset.scrollY || '0', 10);
      document.body.classList.remove('app-modal-open');
      document.body.style.removeProperty('--app-scroll-top');
      delete document.body.dataset.scrollY;
      window.scrollTo(0, scrollY);
    };

    document.addEventListener('DOMContentLoaded', function () {
      window.bindFlashCloseHandlers(document);
    });
  </script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background-color: #1864ab;">
  <a class="navbar-brand" href="index.php" style="font-weight: bold;">GVS BIOMETRIC</a>
  <div class="collapse navbar-collapse justify-content-center">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" href="index.php">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="white" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
          </svg>
          <strong style="color: white;">HOME</strong>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="manage_employees.php">
          <svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 24 24">
            <path fill="white" d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0a4.125 4.125 0 0 1-8.25 0m9.75 2.25a3.375 3.375 0 1 1 6.75 0a3.375 3.375 0 0 1-6.75 0M1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63a13.07 13.07 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63zm15.75.003l-.001.144a2.25 2.25 0 0 1-.233.96q.302.018.609.018c1.596 0 3.107-.37 4.451-1.029a.75.75 0 0 0 .42-.642l.004-.204a4.875 4.875 0 0 0-6.961-4.407a8.6 8.6 0 0 1 1.71 5.157z"/>
          </svg>
          <strong style="color: white;">MANAGE EMPLOYEES</strong>
        </a>
      </li>
    </ul>
  </div>
  <img src="image/gvs.png" style="width: 100px;">
</nav>
<div class="container">
  <div id="appFlashContainer"><?php echo app_consume_flash(); ?></div>

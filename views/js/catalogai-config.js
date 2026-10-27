$(document).ready(function () {
  var $btn = $('#catalogai-test-connection');
  if ($btn.length === 0) {
    return;
  }

  function removePsAlerts() {
    $('#content .bootstrap .alert.alert-success, #content .bootstrap .alert.alert-danger, #content .alert.alert-success, #content .alert.alert-danger').each(function () {
      var $alert = $(this);
      if ($alert.text().indexOf('Catalog AI') !== -1 || $alert.closest('form').find('input[name="CATALOGAI_API_URL"]').length) {
        $alert.closest('.bootstrap').remove();
        if ($alert.closest('.bootstrap').length === 0) {
          $alert.remove();
        }
      }
    });
  }

  function showPsSuccess(msg) {
    removePsAlerts();
    var html = '<div class="bootstrap"><div class="alert alert-success"><button type="button" class="close" data-dismiss="alert">&times;</button>' + msg + '</div></div>';
    var $target = $('#content .panel').first();
    if ($target.length === 0) {
      $target = $('#content form').first();
    }
    if ($target.length === 0) {
      $('#content').prepend(html);
    } else {
      $target.before(html);
    }
    $('html, body').animate({scrollTop: 0}, 300);
  }

  function showPsError(msg) {
    removePsAlerts();
    var html = '<div class="bootstrap"><div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">&times;</button>' + msg + '</div></div>';
    var $target = $('#content .panel').first();
    if ($target.length === 0) {
      $target = $('#content form').first();
    }
    if ($target.length === 0) {
      $('#content').prepend(html);
    } else {
      $target.before(html);
    }
    $('html, body').animate({scrollTop: 0}, 300);
  }

  $btn.on('click', function (e) {
    e.preventDefault();

    var apiUrl = $('input[name="CATALOGAI_API_URL"]').val();
    var apiKey = $('input[name="CATALOGAI_API_KEY"]').val();
    var timeout = $('input[name="CATALOGAI_TIMEOUT"]').val();

    if (!apiUrl || apiUrl.trim() === '') {
      showPsError('Por favor, introduce la URL de la API primero.');
      $('input[name="CATALOGAI_API_URL"]').focus();
      return;
    }

    var $apiKeyInput = $('input[name="CATALOGAI_API_KEY"]');
    var placeholderKey = $apiKeyInput.attr('placeholder') || '';
    if (apiKey === '********') {
      apiKey = '';
    }
    if ((!apiKey || apiKey.trim() === '') && placeholderKey.indexOf('*') === -1) {
      showPsError('Por favor, introduce la clave API primero.');
      $apiKeyInput.focus();
      return;
    }

    var original = $btn.html();
    $btn.prop('disabled', true).html('<i class="icon-refresh icon-spin"></i> Probando...');

    var url = typeof catalogaiTestUrl !== 'undefined' ? catalogaiTestUrl : window.location.href;

    $.ajax({
      url: url,
      type: 'POST',
      dataType: 'json',
      data: {
        CATALOGAI_API_URL: apiUrl,
        CATALOGAI_API_KEY: apiKey,
        CATALOGAI_TIMEOUT: timeout
      },
      success: function (res) {
        if (res && res.success) {
          showPsSuccess(res.message || 'Conexión correcta con el servicio Catalog AI.');
        } else {
          var msg = (res && res.message) ? res.message : 'Respuesta inesperada del servicio.';
          showPsError(msg);
        }
      },
      error: function () {
        showPsError('Error de comunicación con el servidor.');
      },
      complete: function () {
        $btn.prop('disabled', false).html(original);
      }
    });
  });
});

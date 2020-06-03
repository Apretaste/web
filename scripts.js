$(document).ready(function () {
  $('#query').keypress(function (e) {
    if (e.keyCode === 13 || e.which === 13) {
      e.preventDefault();
      submit();
    }
  });

  // check/uncheck the save mode switch
  if (settings !== undefined) {
    if (settings.save_mode) {
      $('#saveMode').prop("checked", true);
      $('#saveModeMessage').css('display', 'block');
    }
    else {
      $('#saveMode').prop("checked", false);
      $('#saveModeMessage').css('display', 'none');
    }
  }

  /*if (content !== 'undefined')
  {
    $("#container-frame").attr('srcdoc', '<html><head></head><body>' + content + '</body>');
  }*/

});

function pad(n, width, z) {
  z = z || '0';
  n = n + '';
  return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
}

// search for a website
function submit() {
  // variable to save the ID of the responses
  var query = $('#query').val().trim();

  // do now allow blank searches
  if (query === "") {
    M.toast({html: 'Debe escribir un texto o página a buscar'});
    return false;
  }

  // send information to the backend
  apretaste.send({
    command: "WEB",
    data: {
      query: query
    },
    redirect: true
  });
}

// cut a string
function cut(text) {
  return text.substring(0, 50);
}

// send info from the iframe
function send(json) {
  apretaste.send(json);
}

// open the settings modal
function settingsModal() {
  // open the modal
  var popup = document.getElementById('settings');
  var modal = M.Modal.init(popup);
  modal.open();
}

// update the save mode setting
function setSaveMode() {
  // get the current save mode
  var saveMode = $('#saveMode').prop('checked');

  // send information to the backend
  apretaste.send({
    command: "WEB SET",
    data: {
      save_mode: saveMode
    },
    redirect: false
  });

  // show/display message
  if (saveMode) {
    $('#saveModeMessage').slideDown();
  }
  else {
    $('#saveModeMessage').slideUp();
  }
}

// formats a date and time
function formatDateTime(dateStr) {
  var months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  var date = new Date(dateStr);
  var month = date.getMonth();
  var day = pad(date.getDate(),2);
  var hour = (date.getHours() < 12) ? date.getHours() : date.getHours() - 12;
  var minutes = date.getMinutes();
  if (minutes < 10) {
    minutes = '0' + minutes;
  }
  var amOrPm = (date.getHours() < 12) ? "am" : "pm";
  return day + ' de ' + months[month] + ' a las ' + hour + ':' + minutes + amOrPm;
}

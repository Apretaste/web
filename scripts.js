//
// FUCTIONS FOR THE SERVICE
//

// formats a date and time
function formatDateTime(dateStr) {
	var months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
	var date = new Date(dateStr);
	var month = date.getMonth();
	var day = date.getDate().toString().padStart(2, '0');
	var hour = (date.getHours() < 12) ? date.getHours() : date.getHours() - 12;
	var minutes = date.getMinutes();
	var amOrPm = (date.getHours() < 12) ? "am" : "pm";
	return day + ' de ' + months[month] + ' a las ' + hour + ':' + minutes + amOrPm;
}

// search for a website
function submitUrl() {
	// variable to save the ID of the responses
	var search = $('#search').val().trim();
	var savings = $('#savings').prop('checked');

	// do now allow blank searches
	if(search == "") {
		M.toast({html: 'Debe escribir un texto o página a buscar'});
		return false;
	}


console.log("search: " + search + "; savings: " + savings);
return;

	$('.question').each(function() {
		// check if the item was checked and return the answer ID
		var item = $(this).find("input[name='"+this.id+"']:checked").val();
		answers.push(item);

		// if no checked, scroll to it and clean the responses
		if(item == undefined) {
			// display a message
			M.toast({html: 'Por favor responda todas las preguntas'});

			// scroll to the question
			$("html, body").animate({scrollTop: $(this).offset().top - 100}, 1000);

			// clean the responses list to stop sending
			answers = [];
			return false;
		}
	});

	if(answers.length) {
		// send information to the backend
		apretaste.send({
			"command": "ENCUESTA RESPONDER",
			"data": {answers:answers},
			"redirect": false
		});

		// display the DONE message
		$('#list').hide();
		$('#msg').show();
	}
}

//
// CALLBACKS
//

// redirect to the survey page
function callbackReloadEncuesta() {
	apretaste.send({command: "ENCUESTA"});
}

//
// PROTOTYPES
//

String.prototype.cut = function(text) {
	return text.substring(0, 100);
};
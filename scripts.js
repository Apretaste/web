//
// ON LOAD FUNCTIONS
//

$(document).ready(function(){
	$('#query').keypress(function(e) {
		if (e.keyCode == 13 || event.which == 13) {
			e.preventDefault();
			submit();
		}
	});
});

//
// FUCTIONS FOR THE SERVICE
//

// search for a website
function submit() {
	// variable to save the ID of the responses
	var query = $('#query').val().trim();
	var save = $('#save').prop('checked');

	// do now allow blank searches
	if(query == "") {
		M.toast({html: 'Debe escribir un texto o página a buscar'});
		return false;
	}

	// send information to the backend
	apretaste.send({
		"command": "WEB",
		"data": {query:query, save:save},
		"redirect": true
	});
}
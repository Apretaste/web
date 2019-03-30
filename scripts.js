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

	// encode URL as base64 to avoid break the JSON
	var encodedQuery = window.btoa(query);

	// send information to the backend
	apretaste.send({
		"command": "WEB",
		"data": {query:encodedQuery, save:save},
		"redirect": true
	});
}

// cut a string
function cut(text) {
	return text.substring(0, 50);
}
$(document).ready(function () {
	$('#query').keypress(function (e) {
		if (e.keyCode === 13 || e.which === 13) {
			e.preventDefault();
			submit();
		}
	});
});

// search for a website
function submit() {
	// variable to save the ID of the responses
	var query = $('#query').val().trim();

	// do now allow blank searches
	if (query === "") {
		M.toast({html: 'Escriba un texto o página a buscar'});
		return false;
	}

	// send information to the backend
	apretaste.send({
		command: 'WEB',
		data: {query: query},
		redirect: true
	});
}

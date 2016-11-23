var logLocation = '/var/www/html/bushfire-traffic/log/log_output.log';

Tail = require('tail').Tail;
tail = new Tail(logLocation);

http = require('http');
var app = express();
var server = http.createServer(app);

var io = require('socket.io').listen(server);

//Run when client connects.
io.on('connection', function(socket) {
	console.log('Client connected.');
	tail.on("line", function(data) {
		socket.emit('log_line', data);
	});
	// Disconnect listener
	socket.on('disconnect', function() {
		console.log('Client disconnected.');
	});
});
console.log('Service running...');

server.listen(app.get('port'));
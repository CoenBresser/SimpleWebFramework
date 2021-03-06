#!/usr/bin/env node

var util = require('util'),
    http = require('http'),
    fs = require('fs'),
    url = require('url'),
    events = require('events');

var DEFAULT_PORT = 8000;

if (typeof String.prototype.startsWith != 'function') {
  String.prototype.startsWith = function (str){
    return this.slice(0, str.length) == str;
  };
}
if (typeof String.prototype.endsWith != 'function') {
  String.prototype.endsWith = function (str){
    return this.slice(-str.length) == str;
  };
}

function main(argv) {
  new HttpServer({
    'GET': createServlet(StaticServlet),
    'HEAD': createServlet(StaticServlet),
    'POST': createServlet(StaticServlet),
    'DELETE': createServlet(StaticServlet)
  }).start(Number(argv[2]) || DEFAULT_PORT);
}

function escapeHtml(value) {
  return value.toString().
    replace('<', '&lt;').
    replace('>', '&gt;').
    replace('"', '&quot;');
}

function createServlet(Class) {
  var servlet = new Class();
  return servlet.handleRequest.bind(servlet);
}

/**
 * An Http server implementation that uses a map of methods to decide
 * action routing.
 *
 * @param {Object} Map of method => Handler function
 */
function HttpServer(handlers) {
  this.handlers = handlers;
  this.server = http.createServer(this.handleRequest_.bind(this));
}

HttpServer.prototype.start = function(port) {
  this.port = port;
  this.server.listen(port);
  util.puts('Http Server running at http://localhost:' + port + '/');
};

HttpServer.prototype.parseUrl_ = function(urlString) {
  var parsed = url.parse(urlString);
  parsed.pathname = url.resolve('/', parsed.pathname);
  return url.parse(url.format(parsed), true);
};

HttpServer.prototype.handleRequest_ = function(req, res) {
  var logEntry = req.method + ' ' + req.url;
  if (req.headers['user-agent']) {
    logEntry += ' ' + req.headers['user-agent'];
  }
  util.puts(logEntry);
  req.url = this.parseUrl_(req.url);
  var handler = this.handlers[req.method];
  if (!handler) {
    res.writeHead(501);
    res.end();
  } else {
    handler.call(this, req, res);
  }
};

/**
 * Handles static content.
 */
function StaticServlet() {}

StaticServlet.MimeMap = {
  'txt': 'text/plain',
  'html': 'text/html',
  'css': 'text/css',
  'xml': 'application/xml',
  'json': 'application/json',
  'js': 'application/javascript',
  'jpg': 'image/jpeg',
  'jpeg': 'image/jpeg',
  'gif': 'image/gif',
  'png': 'image/png',
  'svg': 'image/svg+xml'
};

// Should be done using a proper handler i.o. this hack
StaticServlet.prototype.handleRequest = function(req, res) {
  if (req.method === 'GET') { 
    this.handleGetRequest(req, res);
  } else if (req.method === 'POST') {
    this.handlePostRequest(req, res);
  } else if (req.method === 'DELETE') {
    this.handleDeleteRequest(req, res);
  } else {
    res.writeHead(501);
    res.end();
  }
}
StaticServlet.prototype.handleGetRequest = function(req, res) {
  var self = this;
  
  // App specific: todo find out what I fixed here...
  var path = ('./' + req.url.pathname).replace('//','/').replace(/%(..)/g, function(match, hex){
    return String.fromCharCode(parseInt(hex, 16));
  });

  var parts = path.split('/');
  if (parts[parts.length-1].charAt(0) === '.')
    return self.sendForbidden_(req, res, path);

  // App specific check
  if (path.startsWith('./app/data/v1.')) {
    util.puts('In v1.x data directory, do a mapping.');
    // The mapping is easy, add .json at the end
    if (req.url.query.category) {
      util.puts('Category: ' + req.url.query.category);
      path = path + '-' + req.url.query.category;
    }
    path = path + '.json';
    util.puts('Mapped path: ' + path);
  }
  
  if (path.startsWith('./app/data/v2.')) {
    util.puts('In v2.x data directory, do a mapping.');
    // Simple check, sections will be the first to get the data from
    if (path.endsWith('sections')) {
      // Very basic authentication, check if username is not test_deny
      if (!req.headers.authorization || (req.headers.authorization.split('"')[1] == 'test_deny')) {
        res.writeHead(401, "Unauthorized", {'WWW-Authenticate': 'Digest realm="Protected Area",qop="auth",nonce="53e34f1638dde",opaque="2929b8e007e9c3edd69d915068815d71"'});
        res.end();
      } 
      // This is how the server let's the (my) application know which user is loggedin
      res.setHeader('Userid', req.headers.authorization.split('"')[1]);
      util.puts('user detected: ' + req.headers.authorization.split('"')[1]);
    }
    
    // The mapping is easy, add .json at the end unless filtered, then add the id and value
    if (req.url.query.sectionId) {
      util.puts('SectionId: ' + req.url.query.sectionId);
      path = path + '-sectionId-' + req.url.query.sectionId;
    }
    if (req.url.query.category) {
      util.puts('Category: ' + req.url.query.category);
      path = path + '-category-' + req.url.query.category;
    }
    if (path.indexOf('/works/') <= -1) {
      path = path + '.json';
    }
    util.puts('Mapped path: ' + path);
  }
  
  fs.stat(path, function(err, stat) {
    if (err)
      return self.sendMissing_(req, res, path);
    if (stat.isDirectory())
      return self.sendDirectory_(req, res, path);
    return self.sendFile_(req, res, path);
  });
}
StaticServlet.prototype.handlePostRequest = function(req, res) {
  var body = '';
  req.on('data', function (data) {
    body += data;
    // 1e6 === 1 * Math.pow(10, 6) === 1 * 1000000 ~~~ 1MB
    if (body.length > 1e6) { 
      // FLOOD ATTACK OR FAULTY CLIENT, NUKE REQUEST
      request.connection.destroy();
    }
  });
  req.on('end', function () {

    var POST = JSON.parse(body);
    // use POST
    // Switch on captcha value to create test responses
    if (POST.captcha === "0") { 
      res.writeHead(500, "INTERNAL SERVER ERROR", {'Content-Type': 'application/json'});
      res.write('{"errors": [{"message": "Sending email failed"}]}');
    } else if (POST.captcha === "1") {
      res.writeHead(400, "BAD REQUEST", {'Content-Type': 'application/json'});
      res.write('{"errors": [{"field": "captcha", "message": "Mismatch"}]}');
    } else if (POST.captcha === "2") {
      res.writeHead(400, "BAD REQUEST", {'Content-Type': 'application/json'});
      res.write('{"errors": [{"field": "name", "message": "No name"}]}');
    } else if (POST.captcha === "3") {
      res.writeHead(400, "BAD REQUEST", {'Content-Type': 'application/json'});
      res.write('{"errors": [{"field": "email", "message": "No email"}]}');
    } else if (POST.captcha === "4") {
      res.writeHead(400, "BAD REQUEST", {'Content-Type': 'application/json'});
      res.write('{"errors": [{"field": "email", "message": "Bad email"}]}');
    } else if (POST.captcha === "5") {
      res.writeHead(400, "BAD REQUEST", {'Content-Type': 'application/json'});
      res.write('{"errors": [{"field": "message", "message": "No message"}]}');
    } else {
      res.writeHead(200, "OK", {'Content-Type': 'application/json'});
    }
    res.end();
  });
}
StaticServlet.prototype.handleDeleteRequest = function(req, res) {
  // Just say ok
  res.writeHead(200);
  res.end();
}

StaticServlet.prototype.sendError_ = function(req, res, error) {
  res.writeHead(500, {
      'Content-Type': 'text/html'
  });
  res.write('<!doctype html>\n');
  res.write('<title>Internal Server Error</title>\n');
  res.write('<h1>Internal Server Error</h1>');
  res.write('<pre>' + escapeHtml(util.inspect(error)) + '</pre>');
  util.puts('500 Internal Server Error');
  util.puts(util.inspect(error));
};

StaticServlet.prototype.sendMissing_ = function(req, res, path) {
  path = path.substring(1);
  res.writeHead(404, {
      'Content-Type': 'text/html'
  });
  res.write('<!doctype html>\n');
  res.write('<title>404 Not Found</title>\n');
  res.write('<h1>Not Found</h1>');
  res.write(
    '<p>The requested URL ' +
    escapeHtml(path) +
    ' was not found on this server.</p>'
  );
  res.end();
  util.puts('404 Not Found: ' + path);
};

StaticServlet.prototype.sendForbidden_ = function(req, res, path) {
  path = path.substring(1);
  res.writeHead(403, {
      'Content-Type': 'text/html'
  });
  res.write('<!doctype html>\n');
  res.write('<title>403 Forbidden</title>\n');
  res.write('<h1>Forbidden</h1>');
  res.write(
    '<p>You do not have permission to access ' +
    escapeHtml(path) + ' on this server.</p>'
  );
  res.end();
  util.puts('403 Forbidden: ' + path);
};

StaticServlet.prototype.sendRedirect_ = function(req, res, redirectUrl) {
  res.writeHead(301, {
      'Content-Type': 'text/html',
      'Location': redirectUrl
  });
  res.write('<!doctype html>\n');
  res.write('<title>301 Moved Permanently</title>\n');
  res.write('<h1>Moved Permanently</h1>');
  res.write(
    '<p>The document has moved <a href="' +
    redirectUrl +
    '">here</a>.</p>'
  );
  res.end();
  util.puts('301 Moved Permanently: ' + redirectUrl);
};

StaticServlet.prototype.sendFile_ = function(req, res, path) {
  var self = this;
  var file = fs.createReadStream(path);
  res.writeHead(200, {
    'Content-Type': StaticServlet.
      MimeMap[path.split('.').pop()] || 'text/plain'
  });
  if (req.method === 'HEAD') {
    res.end();
  } else {
    file.on('data', res.write.bind(res));
    file.on('close', function() {
      res.end();
    });
    file.on('error', function(error) {
      self.sendError_(req, res, error);
    });
  }
};

StaticServlet.prototype.sendDirectory_ = function(req, res, path) {
  var self = this;
  if (path.match(/[^\/]$/)) {
    req.url.pathname += '/';
    var redirectUrl = url.format(url.parse(url.format(req.url)));
    return self.sendRedirect_(req, res, redirectUrl);
  }
  fs.readdir(path, function(err, files) {
    if (err)
      return self.sendError_(req, res, error);

    if (!files.length)
      return self.writeDirectoryIndex_(req, res, path, []);

    var remaining = files.length;
    files.forEach(function(fileName, index) {
      fs.stat(path + '/' + fileName, function(err, stat) {
        if (err)
          return self.sendError_(req, res, err);
        if (stat.isDirectory()) {
          files[index] = fileName + '/';
        }
        if (!(--remaining))
          return self.writeDirectoryIndex_(req, res, path, files);
      });
    });
  });
};

StaticServlet.prototype.writeDirectoryIndex_ = function(req, res, path, files) {
  path = path.substring(1);
  res.writeHead(200, {
    'Content-Type': 'text/html'
  });
  if (req.method === 'HEAD') {
    res.end();
    return;
  }
  res.write('<!doctype html>\n');
  res.write('<title>' + escapeHtml(path) + '</title>\n');
  res.write('<style>\n');
  res.write('  ol { list-style-type: none; font-size: 1.2em; }\n');
  res.write('</style>\n');
  res.write('<h1>Directory: ' + escapeHtml(path) + '</h1>');
  res.write('<ol>');
  files.forEach(function(fileName) {
    if (fileName.charAt(0) !== '.') {
      res.write('<li><a href="' +
        escapeHtml(fileName) + '">' +
        escapeHtml(fileName) + '</a></li>');
    }
  });
  res.write('</ol>');
  res.end();
};

// Must be last,
main(process.argv);

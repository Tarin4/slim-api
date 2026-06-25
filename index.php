<?php
// 1. GLOBAL BRUTE-FORCE CORS & PREFLIGHT ACCEPTER
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization, *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// 2. Database Connection Helper
function getDB() {
    $db = new PDO('mysql:host=sql308.infinityfree.com;dbname=if0_42268328_books;charset=utf8mb4', 'if0_42268328', 'utmbooks');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}

// 3. Login Endpoint (Supports optional trailing slash)
$loginHandler = function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $email = $data['email'] ?? '';
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, email, role FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $payload = json_encode([
            'access_token' => 'official-token-12345',
            'user' => $user
        ]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
    
    $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
};
$app->post('/auth/login', $loginHandler);
$app->post('/auth/login/', $loginHandler);

// 4. Get Books Endpoint (Supports trailing slash + built-in search filtering)
$getBooksHandler = function (Request $request, Response $response) {
    $db = getDB();
    
    // Automatically capture any search terms sent by the frontend search bar
    $queryParams = $request->getQueryParams();
    $search = $queryParams['search'] ?? $queryParams['query'] ?? $queryParams['title'] ?? '';

    if (!empty($search)) {
        $stmt = $db->prepare("SELECT * FROM books WHERE title LIKE :search OR author LIKE :search");
        $stmt->execute(['search' => '%' . $search . '%']);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->query("SELECT * FROM books");
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $payload = json_encode(['data' => $books]);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
};
$app->get('/api/books', $getBooksHandler);
$app->get('/api/books/', $getBooksHandler);

// 5. Create Book Endpoint (Supports optional trailing slash)
$createBookHandler = function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    $db = getDB();
    
    $stmt = $db->prepare("INSERT INTO books (title, author, year, created_by) VALUES (:title, :author, :year, 1)");
    $stmt->execute([
        'title' => $data['title'] ?? '',
        'author' => $data['author'] ?? '',
        'year' => $data['year'] ?? ''
    ]);
    
    $response->getBody()->write(json_encode(['message' => 'Book created']));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
};
$app->post('/api/books', $createBookHandler);
$app->post('/api/books/', $createBookHandler);

$app->run();
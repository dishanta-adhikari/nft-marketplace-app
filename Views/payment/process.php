<?php

session_start();
require_once __DIR__."/../../App/App.php";
require_once __DIR__."/../../Config/Url.php";

$app = new App();
$conn = $app->connect();

header('Content-Type: application/json');

$userID = $_SESSION['user_id'] ?? null;
$nftID = $_POST['nft_id'] ?? null;

if (!$userID || !$nftID) {
    echo json_encode(['success' => false, 'message' => 'Missing user or NFT ID']);
    exit;
}

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get price and ownership
    $stmt = $conn->prepare("
        SELECT a.Price 
        FROM nft 
        JOIN artwork a ON nft.Artwork_ID = a.Artwork_ID 
        WHERE nft.NFT_ID = ? AND nft.Owner_User_ID = ?
        LIMIT 1
    ");
    $stmt->execute([$nftID, $userID]);
    $nft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$nft) {
        echo json_encode(['success' => false, 'message' => 'NFT not found or not owned']);
        exit;
    }

    $price = $nft['Price'];

    // Insert transaction (using backticks for reserved word)
    $stmt = $conn->prepare("
        INSERT INTO `transaction` (Amount, Sender, Receiver, NFT_ID) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$price, $userID, 0, $nftID]);

    // Update nft to mark as paid
    $stmt = $conn->prepare("UPDATE nft SET Is_Paid = 1 WHERE NFT_ID = ?");
    $stmt->execute([$nftID]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}

<?php

require_once "srv/database.php";

$db = Database::getConnection();

echo "Автоматизация кинотеатра";
$query = $db->query("SELECT * FROM Movies");
$movies = $query->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>movie_id</th><th>title</th><th>duration_minutes</th><th>genre</th><th>is_active</th></tr>";
foreach ($movies as $m) {
    echo "<tr>";
    echo "<td>{$m['movie_id']}</td>";
    echo "<td>{$m['title']}</td>";
    echo "<td>{$m['duration_minutes']}</td>";
    echo "<td>{$m['genre']}</td>";
    echo "<td>{$m['is_active']}</td>";
    echo "</tr>";
}
echo "</table>";

?>
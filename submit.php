<?php
require 'config.php';

$scores = [];

for($i=0; $i<10; $i++){
    $scores[$i] = (int)$_POST["q$i"];
}

$total = array_sum($scores);

$stmt = $pdo->prepare("
INSERT INTO audit_results
(self_awareness, romantic_love, family_friends, love_expression, community,
joy_gratitude, engagement, growth, purpose, achievement, total_score)
VALUES (?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->execute([
    $scores[0],
    $scores[1],
    $scores[2],
    $scores[3],
    $scores[4],
    $scores[5],
    $scores[6],
    $scores[7],
    $scores[8],
    $scores[9],
    $total
]);

// Score Interpretation
if($total >= 40){
    $result = "Strong Overall Happiness & Life Balance";
}
elseif($total >= 30){
    $result = "Good Wellbeing with Growth Opportunities";
}
elseif($total >= 20){
    $result = "Moderate Satisfaction – Improvement Recommended";
}
else{
    $result = "Significant Improvement Areas – Focus Support";
}
?>

<!DOCTYPE html>
<html>
<head>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body{
    background: linear-gradient(to right, #43e97b, #38f9d7);
}
.result-card{
    background:white;
    padding:40px;
    border-radius:15px;
    margin-top:100px;
    box-shadow:0 10px 25px rgba(0,0,0,0.3);
}
</style>
</head>

<body>

<div class="container text-center">
<div class="result-card">

<h2>Your Happiness Score</h2>

<h1 class="display-3 text-success"><?php echo $total; ?>/50</h1>

<h4><?php echo $result; ?></h4>

<a href="index.php" class="btn btn-primary mt-4">Take Again</a>

</div>
</div>

</body>
</html>
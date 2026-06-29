<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Prepare the INSERT statement
    $sql = "INSERT INTO assessments (
        user_id,
        self_awareness_q1, self_awareness_q2, self_awareness_q3,
        self_awareness_rating, self_awareness_notes,
        romantic_q1, romantic_q2, romantic_rating, romantic_notes,
        family_friends_q1, family_friends_q2, family_friends_rating, family_friends_notes,
        love_expression_q1, love_expression_q2, love_expression_rating, love_expression_notes,
        community_q1, community_q2, community_rating, community_notes,
        joy_q1, joy_q2, joy_rating, joy_notes,
        engagement_q1, engagement_q2, engagement_rating, engagement_notes,
        growth_q1, growth_q2, growth_rating, growth_notes,
        purpose_q1, purpose_q2, purpose_rating, purpose_notes,
        achievement_q1, achievement_q2, achievement_rating, achievement_notes,
        top_improvement_areas, next_steps
    ) VALUES (
        :user_id,
        :sa_q1, :sa_q2, :sa_q3, :sa_rating, :sa_notes,
        :rom_q1, :rom_q2, :rom_rating, :rom_notes,
        :fam_q1, :fam_q2, :fam_rating, :fam_notes,
        :love_q1, :love_q2, :love_rating, :love_notes,
        :com_q1, :com_q2, :com_rating, :com_notes,
        :joy_q1, :joy_q2, :joy_rating, :joy_notes,
        :eng_q1, :eng_q2, :eng_rating, :eng_notes,
        :grow_q1, :grow_q2, :grow_rating, :grow_notes,
        :pur_q1, :pur_q2, :pur_rating, :pur_notes,
        :ach_q1, :ach_q2, :ach_rating, :ach_notes,
        :top_areas, :next_steps
    )";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    $stmt->execute([
        'user_id' => DEFAULT_USER_ID,
        
        // Self-Awareness
        'sa_q1' => isset($_POST['self_awareness_q1']) ? 1 : 0,
        'sa_q2' => isset($_POST['self_awareness_q2']) ? 1 : 0,
        'sa_q3' => isset($_POST['self_awareness_q3']) ? 1 : 0,
        'sa_rating' => (int)$_POST['self_awareness_rating'],
        'sa_notes' => $_POST['self_awareness_notes'] ?? '',
        
        // Romantic
        'rom_q1' => isset($_POST['romantic_q1']) ? 1 : 0,
        'rom_q2' => isset($_POST['romantic_q2']) ? 1 : 0,
        'rom_rating' => (int)$_POST['romantic_rating'],
        'rom_notes' => $_POST['romantic_notes'] ?? '',
        
        // Family & Friends
        'fam_q1' => isset($_POST['family_friends_q1']) ? 1 : 0,
        'fam_q2' => isset($_POST['family_friends_q2']) ? 1 : 0,
        'fam_rating' => (int)$_POST['family_friends_rating'],
        'fam_notes' => $_POST['family_friends_notes'] ?? '',
        
        // Love Expression
        'love_q1' => isset($_POST['love_expression_q1']) ? 1 : 0,
        'love_q2' => isset($_POST['love_expression_q2']) ? 1 : 0,
        'love_rating' => (int)$_POST['love_expression_rating'],
        'love_notes' => $_POST['love_expression_notes'] ?? '',
        
        // Community
        'com_q1' => isset($_POST['community_q1']) ? 1 : 0,
        'com_q2' => isset($_POST['community_q2']) ? 1 : 0,
        'com_rating' => (int)$_POST['community_rating'],
        'com_notes' => $_POST['community_notes'] ?? '',
        
        // Joy
        'joy_q1' => isset($_POST['joy_q1']) ? 1 : 0,
        'joy_q2' => isset($_POST['joy_q2']) ? 1 : 0,
        'joy_rating' => (int)$_POST['joy_rating'],
        'joy_notes' => $_POST['joy_notes'] ?? '',
        
        // Engagement
        'eng_q1' => isset($_POST['engagement_q1']) ? 1 : 0,
        'eng_q2' => isset($_POST['engagement_q2']) ? 1 : 0,
        'eng_rating' => (int)$_POST['engagement_rating'],
        'eng_notes' => $_POST['engagement_notes'] ?? '',
        
        // Growth
        'grow_q1' => isset($_POST['growth_q1']) ? 1 : 0,
        'grow_q2' => isset($_POST['growth_q2']) ? 1 : 0,
        'grow_rating' => (int)$_POST['growth_rating'],
        'grow_notes' => $_POST['growth_notes'] ?? '',
        
        // Purpose
        'pur_q1' => isset($_POST['purpose_q1']) ? 1 : 0,
        'pur_q2' => isset($_POST['purpose_q2']) ? 1 : 0,
        'pur_rating' => (int)$_POST['purpose_rating'],
        'pur_notes' => $_POST['purpose_notes'] ?? '',
        
        // Achievement
        'ach_q1' => isset($_POST['achievement_q1']) ? 1 : 0,
        'ach_q2' => isset($_POST['achievement_q2']) ? 1 : 0,
        'ach_rating' => (int)$_POST['achievement_rating'],
        'ach_notes' => $_POST['achievement_notes'] ?? '',
        
        // Action Planning
        'top_areas' => $_POST['top_improvement_areas'] ?? '',
        'next_steps' => $_POST['next_steps'] ?? ''
    ]);
    
    $assessmentId = $pdo->lastInsertId();
    
    // Redirect to results page
    $_SESSION['success_message'] = 'Assessment completed successfully!';
    header("Location: view_results.php?id=$assessmentId");
    exit;
    
} catch (PDOException $e) {
    error_log("Error saving assessment: " . $e->getMessage());
    $_SESSION['error_message'] = 'There was an error saving your assessment. Please try again.';
    header('Location: index.php');
    exit;
}

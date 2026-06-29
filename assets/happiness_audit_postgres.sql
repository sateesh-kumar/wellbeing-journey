-- ============================================================
-- happiness_audit – PostgreSQL conversion
-- Converted from MySQL dump (phpMyAdmin 5.2.1 / MySQL 8.3.0)
-- ============================================================

SET client_encoding = 'UTF8';
BEGIN;

-- --------------------------------------------------------
-- users
-- --------------------------------------------------------
DROP TABLE IF EXISTS users CASCADE;
CREATE TABLE users (
    id         SERIAL PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL UNIQUE,
    email      VARCHAR(255)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    created_at TIMESTAMPTZ   DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (id, name, email, password, created_at) VALUES
(1, 'default_user',     'user@example.com',        '$2y$10$defaulthashfordemopurposes',                        '2026-02-11 11:09:46+00'),
(2, 'R Satheesh kumar', 'skumar140977@gmail.com',  '$2y$10$QhSgbVURgtzjW05GgXprmuYKt95Q7ZthKJ/whdGaZ25BKViFGFRt6', '2026-02-23 11:29:48+00'),
(3, 'Daya Patil',       'dayappatil@gmail.com',    '$2y$10$Gh2zyJfVImXzRRwLQFMxyO1ocUecbyXrsSqYZEz.zriKwWy8hTtje', '2026-02-24 09:39:53+00');

SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

-- --------------------------------------------------------
-- pillars
-- --------------------------------------------------------
DROP TABLE IF EXISTS pillars CASCADE;
CREATE TABLE pillars (
    id          SERIAL PRIMARY KEY,
    label       VARCHAR(100) NOT NULL,
    icon        VARCHAR(10)  NOT NULL,
    color       VARCHAR(7)   NOT NULL,
    description TEXT         NOT NULL,
    link        VARCHAR(255) DEFAULT NULL,
    field       VARCHAR(100) DEFAULT NULL,
    is_active   SMALLINT     NOT NULL DEFAULT 0,
    sort_order  SMALLINT     NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO pillars (id, label, icon, color, description, link, field, is_active, sort_order, created_at) VALUES
(1, 'Connection & relationships',    '♡',  '#C9A84C', 'Deep bonds, intimacy, and the love you share with those closest to you.',                 'connection_and_love.php',        'romantic', 1, 1, '2026-02-26 11:33:00+00'),
(2, 'Growth & Learning',             '🌱', '#4CAF7D', 'Expanding your mind, building new skills, and becoming more fully yourself.',             'growth_and_learning.php',        NULL,       1, 2, '2026-02-26 11:33:00+00'),
(3, 'Contribution & Helping Others', '🤲', '#7B9ED9', 'The meaning you create by giving, serving, and m_aking a difference.',                   'contribution_and_helping.php',   NULL,       1, 3, '2026-02-26 11:33:00+00'),
(4, 'Freedom & Autonomy',            '🕊', '#B08EE0', 'Living and choosing on your own terms, with space to be fully yourself.',                NULL,                             NULL,       1, 4, '2026-02-26 11:33:00+00'),
(5, 'Security & Certainty',          '⚓', '#E08C4A', 'The foundation of safety, stability, and confidence in your life.',                      NULL,                             NULL,       0, 5, '2026-02-26 11:33:00+00'),
(6, 'Nature & Sustainability',       '🌿', '#5BBF8A', 'Your connection to the natural world and living in a way that lasts.',                   NULL,                             NULL,       0, 6, '2026-02-26 11:33:00+00'),
(7, 'Achievement & Mastery',         '🏔', '#D4726A', 'Setting ambitious goals, honing your craft, and reaching new heights.',                  NULL,                             NULL,       0, 7, '2026-02-26 11:33:00+00');

SELECT setval('pillars_id_seq', (SELECT MAX(id) FROM pillars));

-- --------------------------------------------------------
-- questions
-- --------------------------------------------------------
DROP TABLE IF EXISTS questions CASCADE;
CREATE TABLE questions (
    id             SERIAL PRIMARY KEY,
    pillar_id      INTEGER,
    category_field VARCHAR(60)  NOT NULL,
    question_key   VARCHAR(10)  NOT NULL,
    question_text  TEXT         NOT NULL,
    sort_order     SMALLINT     NOT NULL DEFAULT 0,
    updated_at     TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (category_field, question_key)
);

CREATE INDEX idx_questions_pillar ON questions(pillar_id);

INSERT INTO questions (id, pillar_id, category_field, question_key, question_text, sort_order, updated_at) VALUES
(1,  1, 'self_awareness',     'q1', 'I''m aware about my feeling and self needs.',                                                                    1, '2026-02-27 12:28:14+00'),
(2,  1, 'self_awareness',     'q2', 'I practice self-care, reflection, or mindfulness regularly.',                                                    1, '2026-03-04 11:46:49+00'),
(3,  1, 'self_awareness',     'q3', 'I treat myself with kindness and forgive my mistakes.',                                                          1, '2026-03-04 11:46:49+00'),
(4,  1, 'emotional_connection','q1', 'I feel emotionally connected and supported in my life.',                                                        2, '2026-03-04 11:46:49+00'),
(5,  1, 'emotional_connection','q2', 'My relationships brings joy, affection, and fulfillment.',                                                      2, '2026-03-03 14:22:28+00'),
(6,  1, 'family_friends',     'q1', 'I have supportive and trustworthy family and friends.',                                                          3, '2026-03-04 11:46:49+00'),
(7,  1, 'family_friends',     'q2', 'I maintain regular meaningful communication or shared activities with them.',                                    3, '2026-03-04 11:46:49+00'),
(8,  1, 'love_expression',    'q1', 'I regularly express appreciation, affection, and gratitude to others.',                                          4, '2026-03-04 11:46:49+00'),
(9,  1, 'love_expression',    'q2', 'I feel comfortable giving and receiving love in ways that feel authentic.',                                      4, '2026-03-04 11:46:49+00'),
(10, 1, 'community',          'q1', 'I feel a sense of belonging in a group, community, or cause I care about.',                                     5, '2026-03-04 11:46:49+00'),
(11, 1, 'community',          'q2', 'I contribute to or participate in something beyond my immediate circle.',                                       5, '2026-03-04 11:46:49+00'),
(12, 2, 'joy',                'q1', 'I experience genuine joy, laughter, and lightness in my daily life.',                                           1, '2026-02-27 13:56:08+00'),
(13, 2, 'joy',                'q2', 'I regularly notice and feel grateful for the good in my life.',                                                  1, '2026-03-04 11:46:49+00'),
(14, 2, 'engagement',         'q1', 'I regularly experience deep focus or flow in activities I find meaningful.',                                     2, '2026-03-04 11:46:50+00'),
(15, 2, 'engagement',         'q2', 'My work or hobbies engage my strengths and hold my full attention.',                                            2, '2026-02-27 13:56:08+00'),
(16, 2, 'growth',             'q1', 'I am aware of my unique strengths and actively use them.',                                                       3, '2026-03-04 11:46:50+00'),
(17, 2, 'growth',             'q2', 'I invest in my learning, skills, or personal development.',                                                      3, '2026-03-04 11:46:50+00'),
(18, 2, 'purpose',            'q1', 'My life feels meaningful and connected to a greater purposee',                                                   4, '2026-03-04 11:46:50+00'),
(19, 2, 'purpose',            'q2', 'I contribute positively through work, service, or help others',                                                  4, '2026-03-04 11:46:50+00'),
(20, 7, 'achievement',        'q1', 'I set and achieve meaningful goals.',                                                                            5, '2026-03-04 11:46:50+00'),
(21, 7, 'achievement',        'q2', 'I learn from challenges and maintain a growth mindset.',                                                         5, '2026-03-04 11:46:50+00'),
(22, 3, 'proactive',          'q1', 'I notice opportunities to help and step in without being asked.',                                               1, '2026-03-03 13:36:11+00'),
(23, 3, 'proactive',          'q2', 'I offer help in ways that empower others rather than create dependency.',                                       1, '2026-03-04 11:46:50+00'),
(24, 3, 'knowledge',          'q1', 'I generously share my skills, insights, and experiences to help others grow.',                                  2, '2026-03-04 11:46:50+00'),
(25, 3, 'generosity',         'q1', 'I actively participate in or contribute to groups and causes that create positive change.',                     3, '2026-03-04 11:46:50+00'),
(26, 3, 'generosity',         'q2', 'I regularly give my time, energy, or resources to support people and initiatives I care about.',                3, '2026-03-04 11:46:50+00'),
(27, 3, 'impact',             'q1', 'My actions are guided by how they can create real, meaningful value for others.',                               4, '2026-03-04 11:46:50+00'),
(28, 3, 'sustainable',        'q1', 'I keep showing up to help others while maintaining healthy boundaries to prevent burnout.',                     5, '2026-03-04 11:46:50+00'),
(29, 4, 'time_freedom',       'q1', 'I have real control over how I spend my time each day.',                                                        1, '2026-03-09 12:27:50+00'),
(30, 4, 'decision_freedom',   'q1', 'My important decisions reflect my own values, not external pressure.',                                         2, '2026-03-09 12:27:50+00'),
(31, 4, 'decision_freedom',   'q2', 'I can comfortably say "no" without guilt or fear.',                                                             2, '2026-03-09 12:27:50+00'),
(32, 4, 'lifestyle_freedom',  'q1', 'My environment supports the life I want instead of restricting it.',                                           3, '2026-03-09 12:27:50+00'),
(33, 4, 'financial_autonomy', 'q1', 'My finances give me real options rather than forcing me into unwanted situations.',                             4, '2026-03-09 12:27:50+00'),
(34, 4, 'value_alignment',    'q1', 'I live mostly by my own values, not others'' expectations.',                                                    5, '2026-03-09 12:27:50+00'),
(35, 4, 'value_alignment',    'q2', 'I feel mentally free — not weighed down by constant "shoulds" or fears or obligations.',                        5, '2026-03-09 12:27:50+00');

SELECT setval('questions_id_seq', (SELECT MAX(id) FROM questions));

-- --------------------------------------------------------
-- connection_love_assessments
-- --------------------------------------------------------
DROP TABLE IF EXISTS connection_love_assessments CASCADE;
CREATE TABLE connection_love_assessments (
    id                          SERIAL PRIMARY KEY,
    user_id                     INTEGER,
    assessment_date             TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    self_awareness_q1           SMALLINT     DEFAULT 0,
    self_awareness_q2           SMALLINT     DEFAULT 0,
    self_awareness_q3           SMALLINT     DEFAULT 0,
    self_awareness_rating       SMALLINT     DEFAULT NULL,
    self_awareness_notes        TEXT,
    emotional_connection_q1     SMALLINT     DEFAULT 0,
    emotional_connection_q2     SMALLINT     DEFAULT 0,
    emotional_connection_rating SMALLINT     DEFAULT NULL,
    emotional_connection_notes  TEXT,
    family_friends_q1           SMALLINT     DEFAULT 0,
    family_friends_q2           SMALLINT     DEFAULT 0,
    family_friends_rating       SMALLINT     DEFAULT NULL,
    family_friends_notes        TEXT,
    love_expression_q1          SMALLINT     DEFAULT 0,
    love_expression_q2          SMALLINT     DEFAULT 0,
    love_expression_rating      SMALLINT     DEFAULT NULL,
    love_expression_notes       TEXT,
    community_q1                SMALLINT     DEFAULT 0,
    community_q2                SMALLINT     DEFAULT 0,
    community_rating            SMALLINT     DEFAULT NULL,
    community_notes             TEXT,
    top_improvement_areas       TEXT,
    next_steps                  TEXT
);

CREATE INDEX idx_cla_user_date ON connection_love_assessments(user_id, assessment_date);
CREATE INDEX idx_cla_date      ON connection_love_assessments(assessment_date);

INSERT INTO connection_love_assessments
    (id, user_id, assessment_date, self_awareness_q1, self_awareness_q2, self_awareness_q3, self_awareness_rating, self_awareness_notes,
     emotional_connection_q1, emotional_connection_q2, emotional_connection_rating, emotional_connection_notes,
     family_friends_q1, family_friends_q2, family_friends_rating, family_friends_notes,
     love_expression_q1, love_expression_q2, love_expression_rating, love_expression_notes,
     community_q1, community_q2, community_rating, community_notes,
     top_improvement_areas, next_steps)
VALUES
(3, 2, '2026-03-04 12:27:02+00', 1, 1, 1, 3, '', 1, 1, 4, '', 0, 1, 5, '', 1, 1, 5, '', 0, 1, 3, '',
 E'i should meet my friends often\r\nand connect with them personally',
 E'1. Meet friends and spend time with them\r\n2. Have good thoughts and feelings'),
(4, 3, '2026-03-04 12:37:32+00', 1, 1, 1, 4, '', 1, 0, 4, '', 1, 1, 2, 'Needs to improve on this', 1, 1, 4, '', 1, 1, 4, '', '', '');

SELECT setval('connection_love_assessments_id_seq', (SELECT MAX(id) FROM connection_love_assessments));

-- --------------------------------------------------------
-- contribution_assessments
-- --------------------------------------------------------
DROP TABLE IF EXISTS contribution_assessments CASCADE;
CREATE TABLE contribution_assessments (
    id                     SERIAL PRIMARY KEY,
    user_id                INTEGER,
    assessment_date        TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    proactive_q1           SMALLINT     DEFAULT 0,
    proactive_q2           SMALLINT     DEFAULT 0,
    proactive_rating       SMALLINT     DEFAULT NULL,
    proactive_notes        TEXT,
    knowledge_q1           SMALLINT     DEFAULT 0,
    knowledge_rating       SMALLINT     DEFAULT NULL,
    knowledge_notes        TEXT,
    generosity_q1          SMALLINT     DEFAULT 0,
    generosity_q2          SMALLINT     DEFAULT 0,
    generosity_rating      SMALLINT     DEFAULT NULL,
    generosity_notes       TEXT,
    impact_q1              SMALLINT     DEFAULT 0,
    impact_rating          SMALLINT     DEFAULT NULL,
    impact_notes           TEXT,
    sustainable_q1         SMALLINT     DEFAULT 0,
    sustainable_rating     SMALLINT     DEFAULT NULL,
    sustainable_notes      TEXT,
    top_improvement_areas  TEXT,
    next_steps             TEXT
);

CREATE INDEX idx_ca_user_date ON contribution_assessments(user_id, assessment_date);
CREATE INDEX idx_ca_date      ON contribution_assessments(assessment_date);

INSERT INTO contribution_assessments
    (id, user_id, assessment_date, proactive_q1, proactive_q2, proactive_rating, proactive_notes,
     knowledge_q1, knowledge_rating, knowledge_notes,
     generosity_q1, generosity_q2, generosity_rating, generosity_notes,
     impact_q1, impact_rating, impact_notes,
     sustainable_q1, sustainable_rating, sustainable_notes,
     top_improvement_areas, next_steps)
VALUES
(2, 2, '2026-03-04 12:28:41+00', 1, 1, 5, '', 1, 5, '', 1, 1, 5, '', 1, 3, '', 1, 3, '', '', ''),
(3, 2, '2026-03-06 11:20:48+00', 1, 1, 4, '', 1, 4, '', 0, 1, 4, '', 1, 4, '', 1, 4, '', '', '');

SELECT setval('contribution_assessments_id_seq', (SELECT MAX(id) FROM contribution_assessments));

-- --------------------------------------------------------
-- freedom_autonomy_assessments
-- --------------------------------------------------------
DROP TABLE IF EXISTS freedom_autonomy_assessments CASCADE;
CREATE TABLE freedom_autonomy_assessments (
    id                        SERIAL PRIMARY KEY,
    user_id                   INTEGER,
    assessment_date           TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    time_freedom_q1           SMALLINT     DEFAULT 0,
    time_freedom_rating       SMALLINT     DEFAULT NULL,
    time_freedom_notes        TEXT,
    decision_freedom_q1       SMALLINT     DEFAULT 0,
    decision_freedom_q2       SMALLINT     DEFAULT 0,
    decision_freedom_rating   SMALLINT     DEFAULT NULL,
    decision_freedom_notes    TEXT,
    lifestyle_freedom_q1      SMALLINT     DEFAULT 0,
    lifestyle_freedom_rating  SMALLINT     DEFAULT NULL,
    lifestyle_freedom_notes   TEXT,
    financial_autonomy_q1     SMALLINT     DEFAULT 0,
    financial_autonomy_rating SMALLINT     DEFAULT NULL,
    financial_autonomy_notes  TEXT,
    value_alignment_q1        SMALLINT     DEFAULT 0,
    value_alignment_q2        SMALLINT     DEFAULT 0,
    value_alignment_rating    SMALLINT     DEFAULT NULL,
    value_alignment_notes     TEXT,
    top_improvement_areas     TEXT,
    next_steps                TEXT
);

CREATE INDEX idx_faa_user_date ON freedom_autonomy_assessments(user_id, assessment_date);
CREATE INDEX idx_faa_date      ON freedom_autonomy_assessments(assessment_date);

INSERT INTO freedom_autonomy_assessments
    (id, user_id, assessment_date, time_freedom_q1, time_freedom_rating, time_freedom_notes,
     decision_freedom_q1, decision_freedom_q2, decision_freedom_rating, decision_freedom_notes,
     lifestyle_freedom_q1, lifestyle_freedom_rating, lifestyle_freedom_notes,
     financial_autonomy_q1, financial_autonomy_rating, financial_autonomy_notes,
     value_alignment_q1, value_alignment_q2, value_alignment_rating, value_alignment_notes,
     top_improvement_areas, next_steps)
VALUES
(1, 2, '2026-03-09 12:42:46+00', 1, 4, '', 1, 1, 4, '', 1, 4, '', 1, 4, '', 1, 1, 4, '', '', ''),
(2, 2, '2026-03-11 10:47:43+00', 1, 5, '', 1, 1, 5, '', 1, 5, '', 1, 5, '', 1, 1, 5, '', '', '');

SELECT setval('freedom_autonomy_assessments_id_seq', (SELECT MAX(id) FROM freedom_autonomy_assessments));

-- --------------------------------------------------------
-- growth_learning_assessments
-- --------------------------------------------------------
DROP TABLE IF EXISTS growth_learning_assessments CASCADE;
CREATE TABLE growth_learning_assessments (
    id                     SERIAL PRIMARY KEY,
    user_id                INTEGER,
    assessment_date        TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    joy_q1                 SMALLINT     DEFAULT 0,
    joy_q2                 SMALLINT     DEFAULT 0,
    joy_rating             SMALLINT     DEFAULT NULL,
    joy_notes              TEXT,
    engagement_q1          SMALLINT     DEFAULT 0,
    engagement_q2          SMALLINT     DEFAULT 0,
    engagement_rating      SMALLINT     DEFAULT NULL,
    engagement_notes       TEXT,
    growth_q1              SMALLINT     DEFAULT 0,
    growth_q2              SMALLINT     DEFAULT 0,
    growth_rating          SMALLINT     DEFAULT NULL,
    growth_notes           TEXT,
    purpose_q1             SMALLINT     DEFAULT 0,
    purpose_q2             SMALLINT     DEFAULT 0,
    purpose_rating         SMALLINT     DEFAULT NULL,
    purpose_notes          TEXT,
    achievement_q1         SMALLINT     DEFAULT 0,
    achievement_q2         SMALLINT     DEFAULT 0,
    achievement_rating     SMALLINT     DEFAULT NULL,
    achievement_notes      TEXT,
    top_improvement_areas  TEXT,
    next_steps             TEXT
);

CREATE INDEX idx_gla_user_date ON growth_learning_assessments(user_id, assessment_date);
CREATE INDEX idx_gla_date      ON growth_learning_assessments(assessment_date);

INSERT INTO growth_learning_assessments
    (id, user_id, assessment_date, joy_q1, joy_q2, joy_rating, joy_notes,
     engagement_q1, engagement_q2, engagement_rating, engagement_notes,
     growth_q1, growth_q2, growth_rating, growth_notes,
     purpose_q1, purpose_q2, purpose_rating, purpose_notes,
     achievement_q1, achievement_q2, achievement_rating, achievement_notes,
     top_improvement_areas, next_steps)
VALUES
(4, 2, '2026-03-04 12:24:03+00', 0, 1, 4, '', 0, 1, 4, '', 1, 1, 5, '', 0, 1, 5, '', 1, 1, 3, '', '', ''),
(5, 3, '2026-03-04 12:39:03+00', 0, 1, 4, '', 1, 1, 3, '', 1, 0, 4, '', 1, 0, 4, '', 1, 0, 5, '', '', ''),
(6, 2, '2026-03-06 11:25:14+00', 1, 0, 3, '', 1, 0, 3, '', 1, 0, 3, '', 1, 0, 3, '', 1, 0, 3, '', '', ''),
(7, 2, '2026-03-06 14:07:08+00', 1, 0, 1, '', 1, 0, 3, '', 0, 1, 4, '', 1, 0, 4, '', 0, 1, 1, '', '', '');

SELECT setval('growth_learning_assessments_id_seq', (SELECT MAX(id) FROM growth_learning_assessments));

-- --------------------------------------------------------
-- total_scores
-- --------------------------------------------------------
DROP TABLE IF EXISTS total_scores CASCADE;
CREATE TABLE total_scores (
    id          SERIAL PRIMARY KEY,
    user_id     INTEGER     NOT NULL,
    pillar_id   INTEGER     NOT NULL,
    total_score SMALLINT    NOT NULL DEFAULT 0,
    recorded_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO total_scores (id, user_id, pillar_id, total_score, recorded_at) VALUES
(7,  2, 2, 21, '2026-03-04 12:24:03+00'),
(8,  2, 1, 20, '2026-03-04 12:27:02+00'),
(9,  2, 3, 21, '2026-03-04 12:28:41+00'),
(10, 3, 1, 18, '2026-03-04 12:37:32+00'),
(11, 3, 2, 20, '2026-03-04 12:39:03+00'),
(12, 2, 3, 20, '2026-03-06 11:20:48+00'),
(13, 2, 2, 15, '2026-03-06 11:25:14+00'),
(14, 2, 2, 13, '2026-03-06 14:07:08+00'),
(15, 2, 4, 20, '2026-03-09 12:42:46+00'),
(16, 2, 4, 25, '2026-03-11 10:47:43+00');

SELECT setval('total_scores_id_seq', (SELECT MAX(id) FROM total_scores));

COMMIT;

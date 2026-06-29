<?php
/**
 * Wellbeing Assessment Recommendations Engine
 * Generates personalized recommendations based on assessment scores
 */

class RecommendationsEngine {
    
    /**
     * Generate recommendations for Growth & Learning assessment
     */
    public static function getGrowthRecommendations(array $assessment): array {
        $recommendations = [
            'priority' => [],
            'maintain' => [],
            'celebrate' => []
        ];
        
        $categories = [
            'curiosity_mindset' => [
                'name' => 'Curiosity & Mindset',
                'low' => [
                    'Follow your wonder: List 3 topics that genuinely fascinate you and spend 15 minutes exploring one this week',
                    'Try "learning dates": Schedule weekly time to explore something just for fun, with no productivity pressure',
                    'Approach the unfamiliar gently: When you notice avoidance of a new topic, get curious — ask "what is one thing I could learn about this today?"',
                ],
                'medium' => [
                    'Deepen exploration: Pick one interest and go deeper — read books, take a course, seek out experts',
                    'Cross-pollinate ideas: Combine two of your interests to discover unexpected connections and insights',
                    'Create a curiosity log: Keep a running list of questions, quotes, and ideas that spark your thinking',
                ],
                'high' => [
                    'Share your curiosity: Start a blog, podcast, or discussion group around something you love exploring',
                    'Become intentionally broad: Develop expertise across multiple diverse domains and notice how they inform each other',
                    'Inspire curiosity in others: Model enthusiastic, open-minded learning in your community and relationships',
                ]
            ],
            'skill_building' => [
                'name' => 'Skill Building',
                'low' => [
                    'Commit to one skill: Choose a single meaningful skill and dedicate 15 minutes of deliberate practice daily for the next 30 days',
                    'Find a structured resource: Pick one course, book, or mentor that can give your development direction and focus',
                    'Anchor it to a habit: Attach practice to an existing routine — after morning coffee or before lunch — to make it easier to sustain',
                ],
                'medium' => [
                    'Practise at your edge: Consistently push slightly beyond what feels comfortable — mastery comes from challenge, not just repetition',
                    'Seek honest feedback: Ask a mentor, colleague, or peer for candid input on your progress and use it to adjust your approach',
                    'Build a 6-month learning plan: Map the specific competencies you want and the milestones that will tell you you are improving',
                ],
                'high' => [
                    'Pursue genuine expertise: Commit to deep, sustained practice — read widely, seek masters, and aim for the highest levels of your craft',
                    'Teach what you know: Running a workshop, writing content, or mentoring others is one of the fastest ways to deepen your own mastery',
                    'Expand to adjacent skills: Use your existing strengths as a launchpad to develop complementary capabilities that amplify your impact',
                ]
            ],
            'reflection_learning' => [
                'name' => 'Reflection & Learning',
                'low' => [
                    'End-of-day reflection: Spend 5 minutes each evening noting one thing you learned — from a success, a mistake, or an unexpected moment',
                    'Start a learning log: Write briefly after meaningful experiences to capture what happened, what worked, and what you would do differently',
                    'Read or listen widely: Engage with one book, podcast, or article per week that stretches your thinking beyond your usual topics',
                ],
                'medium' => [
                    'Seek feedback actively: Ask someone you trust for honest input on an area you are working to improve and act on what they share',
                    'Weekly review practice: Set aside 20-30 minutes each week to reflect on what you learned, what patterns you notice, and what to adjust',
                    'Extract lessons from setbacks: After a disappointment or failure, write out what it taught you — this turns experience into wisdom',
                ],
                'high' => [
                    'Develop your learning philosophy: Write out your personal principles for how you grow, reflect, and extract meaning from experience',
                    'Share your insights: Blog, journal publicly, or teach others the lessons and frameworks you have built from your own reflection practice',
                    'Help others reflect: Guide people around you in pausing to learn from their experiences rather than rushing to the next thing',
                ]
            ],
            'growth_mindset' => [
                'name' => 'Growth Mindset',
                'low' => [
                    'Reframe one challenge: When something feels hard, replace "I can\'t do this" with "I can\'t do this yet" — and mean it',
                    'Embrace one uncomfortable thing: Choose one small thing outside your comfort zone this week and do it deliberately',
                    'Celebrate effort, not just outcome: At the end of each day, acknowledge one thing you genuinely tried hard at, regardless of result',
                ],
                'medium' => [
                    'Increase the difficulty: Raise the challenge level of your current efforts by 10-20% — growth lives just past the edge of comfort',
                    'Join a learning community: Find others working on similar goals for motivation, accountability, and shared perspective',
                    'Track your mindset moments: Keep a short log of times you chose growth over comfort — patterns build identity',
                ],
                'high' => [
                    'Model a growth mindset visibly: Share your struggles and learning process openly — others grow when they see that effort is normal',
                    'Explore unfamiliar domains: Take your growth-oriented approach into a completely new area to stretch yourself in fresh ways',
                    'Mentor others through difficulty: Help people reframe setbacks and develop the belief that their abilities can genuinely grow',
                ]
            ],
            'purpose_meaning' => [
                'name' => 'Purpose & Meaning',
                'low' => [
                    'Connect learning to your why: Write down how what you are currently working on serves something larger that matters to you',
                    'Explore what energises you: Reflect on which activities, projects, or topics leave you feeling alive and oriented — follow those signals',
                    'Write a one-sentence direction: Craft a simple statement of what you are moving toward and why — revisit it weekly',
                ],
                'medium' => [
                    'Audit your time and energy: Review how you spend your days and honestly assess how much of it aligns with what you truly value',
                    'Define your purpose clearly: Write a short personal purpose statement — not a job title, but a description of what you are here to do and why it matters',
                    'Align your goals to your values: For each major goal you are pursuing, ask whether it is genuinely yours or shaped by external expectation',
                ],
                'high' => [
                    'Live it more fully: With purpose clear, look for one more way to express it in your daily life — in your work, relationships, or creative output',
                    'Scale your meaning: Find ways to extend your purpose beyond yourself through leadership, teaching, or building something that outlasts you',
                    'Help others find theirs: Guide people around you in connecting their efforts to a deeper sense of direction and meaning',
                ]
            ],
        ];
        
        // Analyze each category
        foreach ($categories as $field => $data) {
            $rating = (int)($assessment[$field . '_rating'] ?? 0);
            
            if ($rating === 0) continue; // Skip unrated categories
            
            if ($rating <= 2) {
                // Priority area - needs work
                $recommendations['priority'][] = [
                    'category' => $data['name'],
                    'rating' => $rating,
                    'suggestions' => $data['low']
                ];
            } elseif ($rating === 3) {
                // Maintain - doing okay
                $recommendations['maintain'][] = [
                    'category' => $data['name'],
                    'rating' => $rating,
                    'suggestions' => $data['medium']
                ];
            } else {
                // Celebrate - doing great
                $recommendations['celebrate'][] = [
                    'category' => $data['name'],
                    'rating' => $rating,
                    'suggestions' => $data['high']
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Generate recommendations for Connection & Love assessment
     */
    public static function getConnectionRecommendations(array $assessment): array {
        $recommendations = [
            'priority' => [],
            'maintain' => [],
            'celebrate' => []
        ];
        
        $categories = [
            'self_awareness' => [
                'name' => 'Self-Awareness & Self-Compassion',
                'low' => [
                    'Start a feelings journal: Each evening, name 3 emotions you felt that day and what triggered them',
                    'Practice self-compassion: When you make a mistake, talk to yourself like you would a good friend',
                    'Try guided meditation: Use apps like Headspace or Calm for 5-10 minutes daily'
                ],
                'medium' => [
                    'Explore your values: List your top 5 values and check if your life aligns with them',
                    'Work with a therapist: Professional support can deepen self-awareness significantly',
                    'Practice mindful check-ins: Pause 3x daily to notice your physical and emotional state'
                ],
                'high' => [
                    'Deepen your practice: Explore advanced meditation, therapy, or self-inquiry methods',
                    'Help others develop awareness: Share your practices or mentor someone on their journey',
                    'Write about your journey: Document your self-discovery to help others and reinforce your own learning'
                ]
            ],
            'romantic' => [
                'name' => 'Romantic Love & Intimacy',
                'low' => [
                    'Schedule connection time: Block out 30-60 minutes weekly for quality time with your partner (or self-care if single)',
                    'Learn love languages: Read "The 5 Love Languages" and discuss with your partner',
                    'Seek support: Consider couples therapy or relationship coaching for new tools and perspectives'
                ],
                'medium' => [
                    'Deepen emotional intimacy: Practice sharing deeper feelings, fears, and dreams with your partner',
                    'Try new experiences together: Novelty and adventure strengthen romantic bonds',
                    'Invest in your relationship: Take a couples workshop, weekend retreat, or read relationship books together'
                ],
                'high' => [
                    'Maintain momentum: Don\'t let complacency set in—keep dating and surprising each other',
                    'Support other couples: Share what works in your relationship to help others',
                    'Explore deeper connection: Consider tantric practices, couples meditation, or other intimacy-deepening work'
                ]
            ],
            'family_friends' => [
                'name' => 'Family & Friend Relationships',
                'low' => [
                    'Reach out to one person: Text or call someone you\'ve been meaning to connect with',
                    'Schedule regular check-ins: Set monthly coffee dates or calls with important people',
                    'Be vulnerable: Share something real about your life with a friend—vulnerability builds connection'
                ],
                'medium' => [
                    'Deepen existing friendships: Move beyond surface conversations to meaningful topics',
                    'Create friend traditions: Establish regular rituals (monthly dinners, annual trips, etc.)',
                    'Repair strained relationships: If possible, reach out to heal rifts with important people'
                ],
                'high' => [
                    'Be intentional: Continue prioritizing these relationships even when life gets busy',
                    'Create community: Bring your friends together, host gatherings, build a wider circle',
                    'Model healthy relationships: Show others what good friendships and family bonds look like'
                ]
            ],
            'love_expression' => [
                'name' => 'Love Expression',
                'low' => [
                    'Start with gratitude: Send one appreciation text daily to someone in your life',
                    'Practice saying it: Tell someone "I love you" or "I appreciate you" this week',
                    'Write thank-you notes: Send 2-3 handwritten notes of appreciation this month'
                ],
                'medium' => [
                    'Diversify expression: Learn to show love through actions, words, gifts, time, and touch',
                    'Be specific: Instead of "thanks," say "I appreciate when you..."',
                    'Make it routine: Build regular practices of expressing love and appreciation'
                ],
                'high' => [
                    'Keep it authentic: Maintain genuine expression without it becoming rote',
                    'Teach others: Help people learn to express love more freely',
                    'Create culture of appreciation: Make expressing love normal in your family/community'
                ]
            ],
            'community' => [
                'name' => 'Community & Belonging',
                'low' => [
                    'Find your people: Identify one group that shares your interests and attend a meeting',
                    'Say yes to invitations: Accept the next social invite you receive, even if it feels awkward',
                    'Volunteer 2 hours: Contributing to others is a fast path to belonging'
                ],
                'medium' => [
                    'Commit to one community: Show up regularly to one group or organization',
                    'Take initiative: Organize events, start conversations, bring people together',
                    'Deepen involvement: Join a committee, volunteer to lead, or take on more responsibility'
                ],
                'high' => [
                    'Build community: Create new gatherings or groups for others to find belonging',
                    'Mentor newcomers: Help others integrate into communities you\'re part of',
                    'Bridge communities: Connect different groups you belong to'
                ]
            ],
        ];
        
        // Analyze each category
        foreach ($categories as $field => $data) {
            $rating = (int)($assessment[$field . '_rating'] ?? 0);
            
            if ($rating === 0) continue;
            
            if ($rating <= 2) {
                $recommendations['priority'][] = [
                    'category' => $data['name'],
                    'rating' => $rating,
                    'suggestions' => $data['low']
                ];
            } elseif ($rating === 3) {
                $recommendations['maintain'][] = [
                    'category' => $data['name'],
                    'rating' => $rating,
                    'suggestions' => $data['medium']
                ];
            } else {
                $recommendations['celebrate'][] = [
                    'category' => $data['name'],
                    'rating' => $rating,
                    'suggestions' => $data['high']
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Generate recommendations for Contribution & Helping Others assessment
     */
    public static function getContributionRecommendations(array $assessment): array {
        $recommendations = [
            'priority'  => [],
            'maintain'  => [],
            'celebrate' => []
        ];

        $categories = [
            'proactive_help' => [
                'name' => 'Proactive Helping',
                'low' => [
                    'Notice one opportunity today: Look for a single moment where you could help without being asked',
                    'Ask how can I help: Make it a habit to check in with one person each week',
                    'Empower not enable: When helping, ask yourself if this builds the other person\'s independence',
                ],
                'medium' => [
                    'Develop your helping radar: Reflect on patterns of who you help most and why',
                    'Help before being asked: Anticipate needs in your close relationships and act early',
                    'Practise empowering help: Coach someone through a challenge rather than solving it for them',
                ],
                'high' => [
                    'Systemise your helpfulness: Create habits or rituals that ensure you show up consistently',
                    'Expand your reach: Look for opportunities to help people outside your immediate circle',
                    'Model proactive helping: Share your approach and inspire others to do the same',
                ]
            ],
            'knowledge_sharing' => [
                'name' => 'Sharing Knowledge & Mentoring',
                'low' => [
                    'Share one insight this week: Send a useful article, tip, or idea to someone who would benefit',
                    'Offer your skills: Identify one skill you have that others around you could use',
                    'Start small mentoring: Have one informal conversation guiding someone on a challenge they face',
                ],
                'medium' => [
                    'Be intentional about teaching: Look for regular moments to share knowledge in conversations',
                    'Create a simple resource: Write a short guide or checklist based on something you know well',
                    'Find a mentee: Commit to a regular monthly check-in with someone you can guide',
                ],
                'high' => [
                    'Build a knowledge-sharing culture: Encourage others in your team or community to share freely',
                    'Scale your mentoring: Run a workshop, write publicly, or create content that helps many people',
                    'Mentor your mentors: Help others develop their own ability to teach and guide',
                ]
            ],
            'generosity' => [
                'name' => 'Generosity & Community Engagement',
                'low' => [
                    'Give one hour this week: Volunteer or donate time to a cause that matters to you',
                    'Identify your cause: Reflect on what communities or issues you genuinely care about',
                    'Start giving regularly: Schedule one volunteer session or contribution practice monthly',
                ],
                'medium' => [
                    'Increase your commitment: Move from occasional to consistent involvement in a cause or group',
                    'Bring others along: Invite a friend or colleague to join you in your community activity',
                    'Give more intentionally: Align your time, energy and resources with your deepest values',
                ],
                'high' => [
                    'Take a leadership role: Step up to organise, lead or grow the communities you contribute to',
                    'Inspire generosity in others: Share stories of impact to motivate people around you',
                    'Create a giving culture: Establish practices in your workplace or family that normalise generosity',
                ]
            ],
            'impact' => [
                'name' => 'Impact-Oriented Contribution',
                'low' => [
                    'Ask what difference does this make: Before acting, consider the real value you\'re creating',
                    'Seek feedback on your impact: Ask someone you\'ve helped what difference it actually made',
                    'Focus your energy: Choose one area where your contribution will have the most meaningful effect',
                ],
                'medium' => [
                    'Measure your impact: Track the tangible outcomes of your contributions over the next 30 days',
                    'Be more intentional: Align your daily actions more consciously with the change you want to create',
                    'Look for leverage: Find ways to multiply your impact through collaboration or delegation',
                ],
                'high' => [
                    'Think bigger: Explore how your contributions could create change at a larger scale',
                    'Document your impact: Share your results to inspire and demonstrate what is possible',
                    'Develop others impact: Help those around you become more purposeful in how they contribute',
                ]
            ],
            'sustainable_service' => [
                'name' => 'Sustainable Helping & Resilience',
                'low' => [
                    'Set one boundary this week: Identify a commitment that is draining you and respectfully reduce it',
                    'Recharge before giving: Prioritise one self-care activity before your next helping commitment',
                    'Say no with kindness: Practise declining one request that does not align with your energy or values',
                ],
                'medium' => [
                    'Audit your commitments: Review what you are giving to and ensure it aligns with your values',
                    'Build recovery time: Schedule regular rest between periods of intense contribution',
                    'Communicate your limits: Let others know clearly what you can and cannot give right now',
                ],
                'high' => [
                    'Model sustainable generosity: Show others how to give wholeheartedly while protecting wellbeing',
                    'Create support systems: Build structures that allow you to keep showing up without burning out',
                    'Mentor others on boundaries: Help those around you learn to give sustainably',
                ]
            ],
        ];

        foreach ($categories as $field => $data) {
            $rating = (int)($assessment[$field . '_rating'] ?? 0);
            if ($rating === 0) continue;
            if ($rating <= 2) {
                $recommendations['priority'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['low']];
            } elseif ($rating === 3) {
                $recommendations['maintain'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['medium']];
            } else {
                $recommendations['celebrate'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['high']];
            }
        }

        return $recommendations;
    }

    /**
     * Generate recommendations for Freedom & Autonomy assessment
     */
    public static function getFreedomRecommendations(array $assessment): array {
        $recommendations = [
            'priority'  => [],
            'maintain'  => [],
            'celebrate' => []
        ];

        $categories = [
            'time_freedom' => [
                'name' => 'Time Freedom',
                'low' => [
                    'Audit your week: Track how you spend your time for 7 days and identify where control is missing',
                    'Block one free hour: Schedule one unstructured hour this week that belongs entirely to you',
                    'Say no to one obligation: Identify a commitment that drains your time without adding value and reduce it',
                ],
                'medium' => [
                    'Design your ideal day: Write out what a truly autonomous day would look like and take one step toward it',
                    'Protect your mornings: Reserve the first hour of your day for something that matters to you',
                    'Batch your tasks: Group similar tasks together to reclaim chunks of uninterrupted time',
                ],
                'high' => [
                    'Build time systems: Create routines and automations that protect your freedom long-term',
                    'Help others reclaim time: Share your strategies for time ownership with someone who needs it',
                    'Design for more freedom: Explore how your work or lifestyle could give you even greater time control',
                ]
            ],
            'decision_freedom' => [
                'name' => 'Decision & Choice Freedom',
                'low' => [
                    'Identify one decision you\'ve been avoiding: Make it this week based on your own values, not others\' expectations',
                    'Practise small nos: Say no to one low-stakes request this week without over-explaining',
                    'Clarify your values: Write down 3 personal values that should guide your important decisions',
                ],
                'medium' => [
                    'Notice external pressure: When making decisions, pause and ask "is this what I actually want?"',
                    'Strengthen your boundaries: Identify one area where you regularly feel pressured and set a clearer limit',
                    'Reflect on recent decisions: Review the last 3 major choices you made — how many truly reflected your values?',
                ],
                'high' => [
                    'Coach others on autonomy: Help someone in your life learn to make decisions from their own values',
                    'Expand your decision-making: Take on a bigger choice that stretches your sense of personal agency',
                    'Model value-led decisions: Share how you make choices and inspire others to do the same',
                ]
            ],
            'lifestyle_freedom' => [
                'name' => 'Location & Lifestyle Freedom',
                'low' => [
                    'Identify one environmental constraint: Name the biggest thing in your environment that limits you and brainstorm one change',
                    'Make one small change: Adjust one aspect of your daily environment to better support your wellbeing',
                    'Explore alternatives: Research one location or lifestyle option that energises you, even if it feels far off',
                ],
                'medium' => [
                    'Plan a lifestyle experiment: Try working or living differently for one week and observe how it feels',
                    'Remove one friction point: Identify something in your environment that drains you and eliminate or reduce it',
                    'Build toward your ideal: Take one concrete step this month toward a lifestyle that better fits you',
                ],
                'high' => [
                    'Design your environment intentionally: Optimise your space and routines to maximise energy and freedom',
                    'Inspire others: Share how you\'ve shaped your environment and encourage others to do the same',
                    'Expand your freedom further: Explore how you could remove even more lifestyle constraints going forward',
                ]
            ],
            'financial_autonomy' => [
                'name' => 'Financial Autonomy',
                'low' => [
                    'Create a simple budget: Track your income and expenses for one month to understand where you stand',
                    'Build a small buffer: Start saving even a small amount each month to create options',
                    'Identify one financial constraint: Name the biggest money pressure in your life and research one way to reduce it',
                ],
                'medium' => [
                    'Set a 6-month financial goal: Define one specific step toward greater financial security',
                    'Reduce one unnecessary expense: Find one cost that limits your freedom and cut or reduce it',
                    'Explore new income options: Research one way to diversify or grow your income over the next year',
                ],
                'high' => [
                    'Accelerate your financial freedom: Set a bold goal to significantly increase your options within 12 months',
                    'Help others build financial awareness: Share what you\'ve learned with someone who could benefit',
                    'Invest in long-term freedom: Put resources toward assets or skills that will compound your autonomy over time',
                ]
            ],
            'value_alignment' => [
                'name' => 'Psychological & Value Alignment',
                'low' => [
                    'Notice one "should": Identify a belief you hold because others expect it — ask if it\'s truly yours',
                    'Write your own rules: List 5 personal values or principles that define how you want to live',
                    'Reduce one obligation: Let go of one commitment you\'ve been carrying out of guilt rather than choice',
                ],
                'medium' => [
                    'Audit your obligations: Review your weekly commitments and mark which ones feel genuinely chosen vs imposed',
                    'Practise mental freedom: When you feel weighed down by "shoulds", pause and ask what you actually want',
                    'Align one area: Choose one part of your life that feels out of step with your values and take a step to realign it',
                ],
                'high' => [
                    'Deepen your self-knowledge: Explore your values further through journaling, therapy, or meaningful conversation',
                    'Live as a model: Show others what it looks like to live by your own values with confidence',
                    'Help others find alignment: Support someone close to you in identifying and living by what truly matters to them',
                ]
            ],
        ];

        foreach ($categories as $field => $data) {
            $rating = (int)($assessment[$field . '_rating'] ?? 0);
            if ($rating === 0) continue;
            if ($rating <= 2) {
                $recommendations['priority'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['low']];
            } elseif ($rating === 3) {
                $recommendations['maintain'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['medium']];
            } else {
                $recommendations['celebrate'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['high']];
            }
        }

        return $recommendations;
    }

    /**
     * Generate recommendations for Security & Stability assessment
     */
    public static function getSecurityRecommendations(array $assessment): array {
        $recommendations = [
            'priority'  => [],
            'maintain'  => [],
            'celebrate' => []
        ];

        $categories = [
            'financial_security' => [
                'name' => 'Financial Security',
                'low' => [
                    'Map your numbers: Write down your monthly income, fixed expenses, and what remains — clarity reduces anxiety',
                    'Build a micro-emergency fund: Set an automatic transfer of even a small amount each month to a separate savings account',
                    'Seek one resource: Book a free session with a financial counsellor or explore a reputable personal finance guide to get started',
                ],
                'medium' => [
                    'Grow your buffer: Work toward 3 months of essential expenses saved as an emergency fund',
                    'Identify your biggest financial risk: Pinpoint the one scenario that worries you most and make a concrete plan to address it',
                    'Review and optimise: Audit your subscriptions, insurance, and recurring costs — redirect freed money toward security',
                ],
                'high' => [
                    'Build long-term resilience: Set a goal to extend your emergency fund to 6 months and begin investing for the future',
                    'Share your financial knowledge: Help a friend or family member start their own budgeting or savings practice',
                    'Protect what you have built: Review your insurance coverage, will, and financial contingency plans to ensure lasting security',
                ]
            ],
            'emotional_safety' => [
                'name' => 'Emotional Safety',
                'low' => [
                    'Name what feels unsafe: Write down one relationship or situation where you do not feel emotionally safe — acknowledgement is the first step',
                    'Create one safe ritual: Identify a daily practice (journalling, a walk, quiet time) where you can be fully yourself without judgement',
                    'Reach out for support: Share how you are feeling with one trusted person or consider speaking with a therapist or counsellor',
                ],
                'medium' => [
                    'Strengthen your boundaries: Identify one relationship where you consistently feel drained or unsafe and communicate a clear, kind limit',
                    'Deepen your safe relationships: Invest time in the connections where you genuinely feel accepted and understood',
                    'Practice self-validation: When you feel judged, pause and remind yourself that your feelings and needs are legitimate',
                ],
                'high' => [
                    'Create safety for others: Use your emotional security to be a dependable, non-judgemental presence for people who need it',
                    'Deepen your self-trust: Explore practices such as therapy, journalling, or meditation that further strengthen your inner security',
                    'Model emotionally safe relationships: Show the people around you what mutual respect and vulnerability look like in practice',
                ]
            ],
            'health_physical_security' => [
                'name' => 'Health & Physical Security',
                'low' => [
                    'Start with one habit: Add a single small health-supporting routine this week — a daily walk, consistent sleep time, or drinking more water',
                    'Address what you have been avoiding: Book that overdue medical appointment or health check-up you have been putting off',
                    'Reduce one physical stressor: Identify the biggest drain on your physical energy and take one small step to address it',
                ],
                'medium' => [
                    'Build consistent health habits: Establish a weekly rhythm of exercise, sleep, and nutrition that sustains your energy reliably',
                    'Know your numbers: Get a routine health check and understand your key health metrics so you can act proactively',
                    'Create a health contingency plan: Ensure you have access to healthcare and know what you would do in a medical emergency',
                ],
                'high' => [
                    'Optimise for longevity: Explore evidence-based practices around sleep, recovery, and preventive health to maintain your wellbeing long-term',
                    'Share your health knowledge: Encourage someone close to you to build better health habits by sharing what works for you',
                    'Become an advocate: Use your physical security as a foundation to support or mentor others who are struggling with their health',
                ]
            ],
            'stability_predictability' => [
                'name' => 'Stability & Predictability',
                'low' => [
                    'Anchor one routine: Choose one consistent daily habit — a regular wake time, a morning ritual, or an evening wind-down — and protect it',
                    'Reduce your largest source of instability: Identify the single area of life that feels most unpredictable and take one concrete step to stabilise it',
                    'Plan your next 7 days: Create a simple weekly schedule that gives your days a reliable shape and reduces decision fatigue',
                ],
                'medium' => [
                    'Design your ideal weekly structure: Map out a weekly rhythm that balances work, rest, relationships, and personal time intentionally',
                    'Build a 90-day plan: Set clear goals across work and personal life for the next three months to increase your sense of direction',
                    'Create contingency plans: For your two or three biggest areas of uncertainty, prepare a simple "if-then" response plan',
                ],
                'high' => [
                    'Help others build structure: Share your routines, planning methods, or systems with someone who is struggling with instability',
                    'Increase your adaptability: Use your stable foundation to practise intentional change — try new approaches while keeping your core anchors intact',
                    'Build systems not just habits: Create documented routines and plans that sustain your stability even during busy or stressful periods',
                ]
            ],
            'security_trust' => [
                'name' => 'Inner Security & Self-Trust',
                'low' => [
                    'Collect evidence of your capability: Write down 5 challenges you have already overcome — proof that you can handle difficulty',
                    'Reduce one uncertainty today: Identify a situation causing anxiety and take one small, concrete action to address it',
                    'Challenge catastrophic thinking: When worry spirals, ask yourself "what is the most realistic outcome?" and focus your energy there',
                ],
                'medium' => [
                    'Strengthen your self-trust: Reflect on decisions you have made well in the past and use those examples to build confidence in your judgement',
                    'Develop a personal coping toolkit: Identify 3 strategies that reliably help you feel grounded when things feel uncertain',
                    'Expand your comfort with uncertainty: Intentionally try one new thing each month that you cannot fully control or predict',
                ],
                'high' => [
                    'Help others build self-trust: Share your perspective on navigating uncertainty with someone who is struggling to feel secure',
                    'Develop deeper resilience: Explore practices such as stoicism, mindfulness, or CBT techniques that further strengthen your inner security',
                    'Use your security as a platform: Take on a bigger challenge or responsibility knowing your inner stability will carry you through it',
                ]
            ],
        ];

        foreach ($categories as $field => $data) {
            $rating = (int)($assessment[$field . '_rating'] ?? 0);
            if ($rating === 0) continue;
            if ($rating <= 2) {
                $recommendations['priority'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['low']];
            } elseif ($rating === 3) {
                $recommendations['maintain'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['medium']];
            } else {
                $recommendations['celebrate'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['high']];
            }
        }

        return $recommendations;
    }

    /**
     * Generate recommendations for Nature & Environment assessment
     */
    public static function getNatureRecommendations(array $assessment): array {
        $recommendations = [
            'priority'  => [],
            'maintain'  => [],
            'celebrate' => []
        ];

        $categories = [
            'nature_connection' => [
                'name' => 'Connection with Nature',
                'low' => [
                    'Take a 10-minute nature break: Step outside each day — even a short walk in a park or time near a window with natural light counts',
                    'Find nature in your city: Identify the closest green space, park, or garden to your home or office and visit it this week',
                    'Bring nature indoors: Add one plant to your living or work space as a simple daily connection with the natural world',
                ],
                'medium' => [
                    'Deepen your outdoor time: Extend your regular nature visits to 30 minutes and leave your phone behind to be fully present',
                    'Explore a new natural setting: Visit a forest, beach, river, or garden you have never been to and pay attention to what you notice',
                    'Practice nature mindfulness: During your time outside, focus on specific sensory details — sounds, textures, smells — to strengthen your connection',
                ],
                'high' => [
                    'Make nature a cornerstone: Build longer, more immersive nature experiences into your monthly routine — hiking, wild swimming, camping',
                    'Share the gift of nature: Take a friend, family member, or colleague to a natural space and introduce them to the benefits you experience',
                    'Deepen your nature knowledge: Learn about local ecosystems, plant species, or wildlife to strengthen your relationship with the natural world',
                ]
            ],
            'sustainable_living' => [
                'name' => 'Sustainable Living',
                'low' => [
                    'Start with one swap: Replace one disposable product in your routine with a reusable alternative this week',
                    'Audit your waste: Spend one week noticing what you throw away and identify the easiest category to reduce',
                    'Take the smallest step: Pick one sustainable habit — shorter showers, turning off unused lights, carrying a reusable bag — and commit to it for 30 days',
                ],
                'medium' => [
                    'Reduce your biggest impact: Identify your largest source of environmental footprint (food, travel, consumption) and set a specific reduction goal',
                    'Build sustainable systems: Make eco-friendly choices automatic — set up recycling stations at home, switch to a green energy provider, buy less but better',
                    'Educate yourself intentionally: Learn about one environmental topic each month to make your choices more informed and impactful',
                ],
                'high' => [
                    'Inspire others to act: Share your sustainable practices with your household, workplace, or community in a way that invites rather than pressures',
                    'Raise your ambition: Set a meaningful sustainability goal for the next 12 months — reducing flights, going plant-based, or achieving a zero-waste month',
                    'Advocate or contribute: Support an environmental organisation, join a local initiative, or use your voice to influence collective change',
                ]
            ],
            'natural_rhythms' => [
                'name' => 'Living by Natural Rhythms',
                'low' => [
                    'Align with daylight: Try waking within 30 minutes of sunrise for one week and notice its effect on your energy and mood',
                    'Build a wind-down ritual: Create a simple 20-minute evening routine that signals to your body that it is time to rest',
                    'Reduce artificial light at night: Dim screens and overhead lighting after sunset for one week and observe any changes in sleep quality',
                ],
                'medium' => [
                    'Sync your routine with seasons: Adjust your sleep times, activity levels, and diet slightly as the seasons change to better match natural cycles',
                    'Spend time in natural light daily: Make a habit of stepping outside in the morning — natural light anchors your circadian rhythm and lifts mood',
                    'Honour rest as productive: Schedule deliberate recovery time each week and treat it with the same intention as your most important commitments',
                ],
                'high' => [
                    'Design a rhythm-centred lifestyle: Intentionally shape your work, rest, and social calendar around natural energy cycles rather than forcing productivity',
                    'Help others reconnect with rhythm: Share what you have learned about natural cycles and their benefits with someone struggling with energy or sleep',
                    'Deepen your seasonal practice: Explore traditions, rituals, or practices tied to seasonal change — solstices, fasting periods, or seasonal foods',
                ]
            ],
            'environmental_awareness' => [
                'name' => 'Environmental Awareness & Contribution',
                'low' => [
                    'Read one credible article: Choose one accessible, solution-focused piece about an environmental topic that interests you this week',
                    'Identify one contribution: Find a single action within your current means — signing a petition, donating, or attending a local clean-up — and do it',
                    'Limit eco-anxiety: If environmental news overwhelms you, set a boundary around consumption and focus on what you can personally influence',
                ],
                'medium' => [
                    'Stay informed without burnout: Follow one or two trusted environmental sources and set a weekly time to engage with their content',
                    'Connect awareness to action: For each environmental issue you learn about, identify one practical thing you can personally do in response',
                    'Join a community effort: Participate in a local environmental group, community garden, or sustainability initiative to make your contribution tangible',
                ],
                'high' => [
                    'Translate awareness into leadership: Use your knowledge to guide others — host a conversation, write about what you have learned, or organise local action',
                    'Deepen your environmental education: Take a course, read widely, or engage with experts to move from awareness to genuine expertise',
                    'Scale your impact: Look for opportunities to influence your workplace, school, or community to adopt more sustainable practices collectively',
                ]
            ],
            'nature_restoration' => [
                'name' => 'Nature as Restoration & Wellbeing',
                'low' => [
                    'Use nature as a reset: The next time you feel stressed or overwhelmed, step outside for 10 minutes before reaching for your phone or other distractions',
                    'Notice one beautiful thing daily: Make it a practice to spot one natural detail each day — a bird, a cloud, a plant — and pause with it for a moment',
                    'Explore the science: Read about the research on nature\'s effects on mental health to build motivation for spending more time outdoors',
                ],
                'medium' => [
                    'Create a restorative nature ritual: Design a regular practice — a morning walk, a weekend hike, or evening time in your garden — that consistently restores you',
                    'Use nature intentionally for stress: When you notice tension building, proactively schedule outdoor time before it escalates into burnout',
                    'Cultivate awe regularly: Seek out experiences of natural grandeur — a starry night, a wide landscape, a powerful storm — that put your worries in perspective',
                ],
                'high' => [
                    'Share nature\'s restorative power: Introduce someone struggling with stress or burnout to outdoor practices that have helped you',
                    'Build a nature-rich life: Make your living and working environments as connected to nature as possible — natural light, plants, views, outdoor breaks',
                    'Deepen your awe practice: Seek increasingly profound nature experiences — wilderness trips, forest bathing, dawn watching — to continue growing your sense of wonder',
                ]
            ],
        ];

        foreach ($categories as $field => $data) {
            $rating = (int)($assessment[$field . '_rating'] ?? 0);
            if ($rating === 0) continue;
            if ($rating <= 2) {
                $recommendations['priority'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['low']];
            } elseif ($rating === 3) {
                $recommendations['maintain'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['medium']];
            } else {
                $recommendations['celebrate'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['high']];
            }
        }

        return $recommendations;
    }

    /**
     * Generate recommendations for Achievement & Mastery assessment
     */
    public static function getAchievementRecommendations(array $assessment): array {
        $recommendations = [
            'priority'  => [],
            'maintain'  => [],
            'celebrate' => []
        ];

        $categories = [
            'goal_setting' => [
                'name' => 'Goal Setting & Progress',
                'low' => [
                    'Set one clear goal for this week: Make it specific, achievable, and personally meaningful — not a task but a real step forward',
                    'Start a progress log: Each evening, write down one thing you moved forward today, however small — visibility builds momentum',
                    'Clarify what you actually want: Spend 20 minutes writing freely about what success looks like across work, health, relationships, and personal growth',
                ],
                'medium' => [
                    'Create a 90-day goal map: Define 1-2 meaningful goals per life area with clear milestones and a weekly review practice',
                    'Track wins deliberately: Use a simple tool — a journal, app, or whiteboard — to record progress weekly and celebrate what is working',
                    'Align your goals with your values: Review your current goals and ask honestly whether each one is truly yours or driven by expectation',
                ],
                'high' => [
                    'Set a bold 12-month vision: Write an ambitious, values-driven goal that would genuinely transform an area of your life and build a plan to pursue it',
                    'Share your goal-setting system: Teach a friend, team member, or mentee the framework that helps you set and pursue meaningful goals',
                    'Review and raise your bar: Schedule a quarterly goal review to assess your growth, close completed goals, and set new ambitious targets',
                ]
            ],
            'skill_development' => [
                'name' => 'Skill Development & Mastery',
                'low' => [
                    'Identify your most valuable skill to grow: Choose one skill that would make the biggest difference to your work or life and commit to 15 minutes of deliberate practice daily',
                    'Find a structured resource: Pick one course, book, or mentor that can give your skill development direction and focus',
                    'Make it a habit: Attach your skill practice to an existing routine — after morning coffee, before lunch — to make it easier to sustain',
                ],
                'medium' => [
                    'Practise at your edge: Consistently push slightly beyond what feels comfortable — mastery requires challenge, not just repetition',
                    'Seek feedback actively: Ask a mentor, colleague, or peer to give you honest input on your skill development and use it to adjust',
                    'Build a 6-month learning plan: Map the specific competencies you want to develop and the resources and milestones that will get you there',
                ],
                'high' => [
                    'Pursue genuine expertise: Commit to deep, sustained practice in your chosen area — read widely, seek masters, and focus on the highest levels of your craft',
                    'Teach what you know: Running a workshop, writing content, or mentoring others is the fastest way to deepen your own mastery',
                    'Expand to adjacent skills: Use your existing mastery as a launchpad to develop complementary capabilities that amplify your impact',
                ]
            ],
            'competence_confidence' => [
                'name' => 'Competence & Confidence',
                'low' => [
                    'Build evidence of competence: Write a list of 10 tasks or challenges you handle well — read it when self-doubt arises',
                    'Take on one slightly bigger task: Choose something just beyond your current comfort zone and complete it — capability is built through action, not waiting',
                    'Reduce comparison: Identify one habit (social media, peer comparisons) that undermines your confidence and set a boundary around it for two weeks',
                ],
                'medium' => [
                    'Act before you feel ready: Practise taking action in areas where you feel only 70% prepared — confidence follows competent action, not the other way around',
                    'Reflect on what you do well: Schedule a monthly self-review to acknowledge your strengths and the progress you have made',
                    'Seek stretch assignments: Volunteer for one project or responsibility that is slightly beyond your current experience to grow your capability and confidence together',
                ],
                'high' => [
                    'Extend your impact: Use your confidence and competence to take on leadership, bigger challenges, or higher-stakes work that demands more of you',
                    'Build others\' confidence: Notice when someone doubts themselves in an area where you see real ability — offer specific, genuine encouragement',
                    'Stay a learner: Use your confidence as a platform to explore new domains rather than resting in what you already know well',
                ]
            ],
            'overcoming_challenges' => [
                'name' => 'Overcoming Challenges & Resilience',
                'low' => [
                    'Reframe one current challenge: Write down a difficulty you are facing and list three things you could learn or gain from it',
                    'Take one small step forward: When a challenge feels paralysing, identify the smallest possible next action and do just that',
                    'Build a support network: Identify one person who has navigated something similar to your current challenge and reach out to learn from their experience',
                ],
                'medium' => [
                    'Develop a challenge-response routine: When setbacks occur, practise a consistent process — pause, reflect, extract the lesson, then re-engage with a revised plan',
                    'Strengthen your resilience deliberately: Add one resilience-building practice to your week — journalling, cold exposure, difficult conversations, physical challenge',
                    'Review your recovery: After your last major setback, reflect on what helped you bounce back and invest more in those strategies going forward',
                ],
                'high' => [
                    'Seek out worthy challenges: Deliberately take on projects, roles, or experiences that will stretch your resilience and deepen your capabilities',
                    'Mentor others through difficulty: When someone faces a setback, share your experience of navigating similar challenges and the perspective you gained',
                    'Build antifragile systems: Design your routines, habits, and commitments so that stress and disruption strengthen rather than derail your progress',
                ]
            ],
            'balance_achievement' => [
                'name' => 'Balanced Achievement & Wellbeing',
                'low' => [
                    'Audit your achievement cost: Honestly assess whether your current drive for results is costing you health, relationships, or enjoyment — name the imbalance',
                    'Schedule genuine rest: Block time this week for something with no productive outcome — rest is not a reward for achievement, it is a foundation for it',
                    'Find joy in the process: Choose one goal you are pursuing and identify something enjoyable about the journey itself, not just the destination',
                ],
                'medium' => [
                    'Design sustainable ambition: Set goals that excite and stretch you without requiring sacrifice of sleep, relationships, or health over the long term',
                    'Build recovery into your rhythm: Schedule regular recovery periods — weekly rest days, quarterly breaks, annual reflection — as non-negotiable parts of your achievement cycle',
                    'Practise present-moment satisfaction: Develop a habit of noticing and appreciating where you are now, even while working toward where you want to go',
                ],
                'high' => [
                    'Model healthy ambition: Show those around you — colleagues, friends, family — what it looks like to pursue meaningful goals without sacrificing wellbeing',
                    'Mentor others on sustainable achievement: Share your approach to balancing drive and rest with someone who is burning out or losing enjoyment in their work',
                    'Explore deeper fulfilment: With external achievement in place, turn your attention inward — explore meaning, contribution, and experiences beyond accomplishment',
                ]
            ],
        ];

        foreach ($categories as $field => $data) {
            $rating = (int)($assessment[$field . '_rating'] ?? 0);
            if ($rating === 0) continue;
            if ($rating <= 2) {
                $recommendations['priority'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['low']];
            } elseif ($rating === 3) {
                $recommendations['maintain'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['medium']];
            } else {
                $recommendations['celebrate'][] = ['category' => $data['name'], 'rating' => $rating, 'suggestions' => $data['high']];
            }
        }

        return $recommendations;
    }

}

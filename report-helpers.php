<?php
declare(strict_types=1);

function report_grade(?float $score): string
{
    if ($score === null) return '—';
    if ($score >= 90) return 'A';
    if ($score >= 80) return 'B';
    if ($score >= 60) return 'C';
    if ($score >= 50) return 'D';
    return 'E';
}

function report_grade_points(?float $score): ?int
{
    $grade = report_grade($score);
    return match ($grade) {
        'A' => 1,
        'B' => 2,
        'C' => 3,
        'D' => 4,
        'E' => 5,
        default => null,
    };
}

function report_division(?int $aggregate, int $subjectCount): string
{
    if ($aggregate === null || $subjectCount < 1) return 'Pending';
    return match (true) {
        $aggregate <= 12 => 'Division I',
        $aggregate <= 24 => 'Division II',
        $aggregate <= 28 => 'Division III',
        $aggregate <= 32 => 'Division IV',
        default => 'Ungraded',
    };
}

function report_remark(?float $score, int $seed): string
{
    if ($score === null) return 'Assessment not yet entered.';
    $choices = match (report_grade($score)) {
        'A' => ['Outstanding work. Keep aiming high.', 'Excellent understanding and effort.', 'A brilliant result; continue this great work.'],
        'B' => ['Very good progress. Keep practising.', 'Strong work and a pleasing result.', 'Good understanding; aim even higher.'],
        'C' => ['Satisfactory progress. More practice will help.', 'A good foundation; keep working steadily.', 'Making progress; revise regularly.'],
        'D' => ['Keep practising the key skills.', 'More revision and support will build confidence.', 'Working towards the expected standard.'],
        default => ['Needs extra practice and teacher support.', 'Please revise regularly and ask for help.', 'More guided practice is needed to strengthen these skills.'],
    };
    return $choices[abs($seed) % count($choices)];
}

function report_overall_comment(?float $average, string $role, int $seed): string
{
    if ($average === null) {
        return $role === 'head'
            ? 'Results are incomplete. Please support regular attendance and completion of class work.'
            : 'Please complete the remaining assessments so progress can be reviewed.';
    }

    if ($average >= 90) {
        $choices = $role === 'head'
            ? ['An exceptional term. Continue to nurture this learner’s excellent habits.', 'Outstanding achievement. Maintain this high standard next term.', 'Excellent results and effort. We are proud of this progress.']
            : ['Excellent work this term. Keep up the outstanding effort.', 'A wonderful term of learning. Continue to challenge yourself.', 'Consistently excellent effort and achievement. Well done.'];
    } elseif ($average >= 80) {
        $choices = $role === 'head'
            ? ['A very strong term. Keep building on this pleasing progress.', 'Very good achievement. Continued effort will bring even more success.', 'A commendable report; maintain these positive learning habits.']
            : ['Very good work. Keep practising and aim even higher.', 'A strong term. Your steady effort is paying off.', 'Good progress across the subjects. Keep it up.'];
    } elseif ($average >= 60) {
        $choices = $role === 'head'
            ? ['A satisfactory term. Regular revision will help strengthen results.', 'Good foundations are developing; consistent support will help.', 'Progress is evident. Encourage daily reading and revision.']
            : ['You are making progress. Keep practising every day.', 'A sound effort; focus on the topics that need more practice.', 'Keep working steadily and ask questions when unsure.'];
    } elseif ($average >= 50) {
        $choices = $role === 'head'
            ? ['More regular practice and home support are recommended next term.', 'Please work closely with the class teacher to strengthen core skills.', 'A developing term. Attendance, practice and support are important.']
            : ['Keep trying. Daily practice will help you improve.', 'Focus on the basics and ask your teacher for help.', 'You can improve with regular practice and careful revision.'];
    } else {
        $choices = $role === 'head'
            ? ['A support plan and regular home practice are recommended.', 'Please meet the class teacher to plan additional learning support.', 'Focused practice and close support are needed next term.']
            : ['Please practise the basics each day with your teacher’s help.', 'Keep attending and ask for support when work feels difficult.', 'Small, regular steps will help you improve.'];
    }

    return $choices[abs($seed) % count($choices)];
}

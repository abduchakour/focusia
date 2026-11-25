<?php

namespace Database\Seeders;

use App\Enums\AttemptStatus;
use App\Enums\Level;
use App\Enums\MaterialType;
use App\Enums\ResourceType;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Group;
use App\Models\Institution;
use App\Models\Material;
use App\Models\Notion;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Resource;
use App\Models\Submission;
use App\Models\User;
use App\Support\Grading;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Realistic demo data for a jury presentation. Deterministic (fixed random seed).
 * Demo passwords are documented in README.md only.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Demo2026!';

    /** @var array<string, Notion> */
    private array $notions = [];

    public function run(): void
    {
        mt_srand(2026);
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($now) {
            Institution::whereKey(Institution::DEFAULT_ID)->update(['name' => 'Université de Djibouti (démo)']);

            [$amina, $omar] = $this->teachers();
            $students = $this->students();

            $groups = $this->groups($amina, $omar, $students, $now);
            $this->notionsFor($amina, $omar);
            $this->quizzes($amina, $omar, $groups, $now);
            $materials = $this->materials($amina, $omar, $groups);
            $this->assignments($amina, $omar, $groups, $materials, $now);
            $this->resources($amina, $omar, $groups);
        });
    }

    /** @return array{User, User} */
    private function teachers(): array
    {
        $password = Hash::make(self::PASSWORD);

        $amina = User::create([
            'first_name' => 'Amina', 'last_name' => 'Hassan', 'email' => 'amina.hassan@focusia.test',
            'password' => $password, 'role' => Role::Teacher,
        ]);
        $omar = User::create([
            'first_name' => 'Omar', 'last_name' => 'Ali Farah', 'email' => 'omar.ali@focusia.test',
            'password' => $password, 'role' => Role::Teacher,
        ]);
        $amina->forceFill(['email_verified_at' => now()])->save();
        $omar->forceFill(['email_verified_at' => now()])->save();

        return [$amina, $omar];
    }

    /** @return Collection<int, User> */
    private function students(): Collection
    {
        $names = [
            ['Ahmed', 'Youssouf'], ['Fatouma', 'Ibrahim'], ['Mohamed', 'Abdillahi'], ['Hodan', 'Robleh'],
            ['Ismaël', 'Daher'], ['Kadra', 'Moussa'], ['Abdourahman', 'Elmi'], ['Nasra', 'Omar'],
            ['Houssein', 'Djama'], ['Safia', 'Aden'], ['Ayanleh', 'Guedi'], ['Roukia', 'Hassan'],
            ['Moustapha', 'Waberi'], ['Deka', 'Ahmed'], ['Idriss', 'Bouh'], ['Zamzam', 'Ali'],
            ['Saïd', 'Kamil'], ['Amran', 'Djibril'], ['Hibo', 'Farah'], ['Yacin', 'Houmed'],
            ['Asma', 'Abdi'], ['Mahdi', 'Osman'], ['Ifrah', 'Mahamoud'], ['Liban', 'Goud'],
            ['Samira', 'Barkat'], ['Omar', 'Dirieh'], ['Halima', 'Ismaël'], ['Abdallah', 'Nour'],
            ['Faïza', 'Bileh'], ['Hamza', 'Ougoureh'],
        ];
        $password = Hash::make(self::PASSWORD);

        return collect($names)->values()->map(function (array $name, int $i) use ($password) {
            $user = User::create([
                'first_name' => $name[0],
                'last_name' => $name[1],
                'email' => sprintf('etudiant%02d@focusia.test', $i + 1),
                'password' => $password,
                'role' => Role::Student,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        });
    }

    /**
     * Students 1-12 in TD1, 13-24 in TD2, 21-30 in the L2 group (21-24 are in two
     * groups, rule 1). Student 24 was removed from TD2 (rule 18 demo).
     *
     * @return array{td1: Group, td2: Group, l2: Group}
     */
    private function groups(User $amina, User $omar, Collection $students, CarbonImmutable $now): array
    {
        $td1 = Group::create(['name' => 'L1 Informatique – TD1', 'subject' => 'Algorithmique', 'level' => Level::L1, 'teacher_id' => $amina->id, 'access_code' => 'ALGO1A']);
        $td2 = Group::create(['name' => 'L1 Informatique – TD2', 'subject' => 'Algorithmique', 'level' => Level::L1, 'teacher_id' => $amina->id, 'access_code' => 'ALGO2B']);
        $l2 = Group::create(['name' => 'L2 Informatique – Bases de données', 'subject' => 'Bases de données', 'level' => Level::L2, 'teacher_id' => $omar->id, 'access_code' => 'BDD2L2']);

        $joined = $now->subWeeks(6);
        foreach ($students->slice(0, 12) as $s) {
            $td1->enrollments()->create(['student_id' => $s->id, 'joined_at' => $joined]);
        }
        foreach ($students->slice(12, 12) as $s) {
            $td2->enrollments()->create([
                'student_id' => $s->id,
                'joined_at' => $joined,
                'removed_at' => $s->is($students[23]) ? $now->subDays(3) : null,
            ]);
        }
        foreach ($students->slice(20, 10) as $s) {
            $l2->enrollments()->create(['student_id' => $s->id, 'joined_at' => $joined]);
        }

        return ['td1' => $td1, 'td2' => $td2, 'l2' => $l2];
    }

    private function notionsFor(User $amina, User $omar): void
    {
        foreach (['variables' => 'Variables et types', 'conditions' => 'Conditions', 'boucles' => 'Boucles', 'tableaux' => 'Tableaux'] as $key => $name) {
            $this->notions[$key] = Notion::create(['teacher_id' => $amina->id, 'subject' => 'Algorithmique', 'name' => $name]);
        }
        foreach (['relationnel' => 'Modèle relationnel', 'sql' => 'Requêtes SQL'] as $key => $name) {
            $this->notions[$key] = Notion::create(['teacher_id' => $omar->id, 'subject' => 'Bases de données', 'name' => $name]);
        }
    }

    /** @param array{td1: Group, td2: Group, l2: Group} $groups */
    private function quizzes(User $amina, User $omar, array $groups, CarbonImmutable $now): void
    {
        $bank = QuestionBank::all();

        // Success probability per notion: "Boucles" is the hard one, so the
        // per-notion chart tells a clear story during the demo.
        $difficulty = ['variables' => 0.85, 'conditions' => 0.72, 'boucles' => 0.42, 'tableaux' => 0.6, 'relationnel' => 0.75, 'sql' => 0.5];

        $specs = [
            ['Quiz 1 – Variables et conditions', $amina, 'Algorithmique', Level::L1, 15, $now->subWeeks(4), [$groups['td1'], $groups['td2']], ['variables' => 4, 'conditions' => 4]],
            ['Quiz 2 – Boucles', $amina, 'Algorithmique', Level::L1, 20, $now->subWeeks(3), [$groups['td1'], $groups['td2']], ['boucles' => 6, 'conditions' => 2, 'variables' => 2]],
            ['Quiz 3 – Tableaux et boucles', $amina, 'Algorithmique', Level::L1, 20, $now->subWeeks(1), [$groups['td1'], $groups['td2']], ['tableaux' => 5, 'boucles' => 5]],
            ['Quiz SQL 1 – Modèle relationnel et requêtes', $omar, 'Bases de données', Level::L2, 15, $now->subWeeks(2), [$groups['l2']], ['relationnel' => 4, 'sql' => 4]],
        ];

        // Each student has a personal level, so a few clearly struggle.
        $ability = [];

        foreach ($specs as $index => [$title, $teacher, $subject, $level, $duration, $dueAt, $targets, $mix]) {
            $quiz = $this->createQuiz($teacher, $title, $subject, $level, $duration, $dueAt, $dueAt->addHour(), $targets, $mix, $bank, $dueAt->subWeek());

            $questions = $quiz->questions()->with(['choices', 'notion'])->get();
            $students = collect($targets)->flatMap(fn (Group $g) => $g->students()->get())->unique('id');

            foreach ($students as $student) {
                // About 1 student in 10 skipped this quiz.
                if (mt_rand(1, 10) === 1) {
                    continue;
                }
                $ability[$student->id] ??= (mt_rand(-25, 15) / 100);

                $startedAt = $dueAt->subDays(mt_rand(1, 5))->setTime(mt_rand(8, 20), mt_rand(0, 59));
                $answeredAt = $startedAt->addMinutes(mt_rand(5, $duration));
                // A few attempts were answered offline and synced after the due date (rule 8).
                $syncedAt = mt_rand(1, 8) === 1 ? $dueAt->addHours(mt_rand(1, 20)) : $answeredAt->addSeconds(mt_rand(1, 30));

                $attempt = Attempt::create([
                    'quiz_id' => $quiz->id, 'student_id' => $student->id, 'status' => AttemptStatus::Submitted,
                    'started_at' => $startedAt, 'answered_at' => $answeredAt, 'synced_at' => $syncedAt,
                ]);

                $correct = 0;
                foreach ($questions as $question) {
                    $notionKey = array_search($question->notion_id, array_map(fn (Notion $n) => $n->id, $this->notions), true);
                    $p = max(0.05, min(0.98, $difficulty[$notionKey] + $ability[$student->id] + $index * 0.02));
                    $isCorrect = mt_rand() / mt_getrandmax() < $p;
                    $choice = $isCorrect
                        ? $question->choices->firstWhere('is_correct', true)
                        : $question->choices->where('is_correct', false)->random();
                    $correct += $isCorrect ? 1 : 0;

                    AttemptAnswer::create([
                        'attempt_id' => $attempt->id, 'question_id' => $question->id,
                        'choice_id' => $choice->id, 'is_correct' => $isCorrect,
                    ]);
                }

                $attempt->update([
                    'correct_count' => $correct,
                    'total_count' => $questions->count(),
                    'score' => Grading::quizScore($correct, $questions->count()),
                ]);
            }
        }

        // One upcoming quiz, so a student can take it live during the demo.
        $this->createQuiz(
            $amina, 'Quiz 4 – Révision avant examen', 'Algorithmique', Level::L1, 15,
            $now->addWeek()->setTime(23, 59), $now->addWeek()->setTime(23, 59)->addDay(),
            [$groups['td1'], $groups['td2']], ['variables' => 1, 'conditions' => 1, 'boucles' => 2, 'tableaux' => 1], $bank, $now->subDay(),
        );
    }

    /**
     * @param  array<int, Group>  $targets
     * @param  array<string, int>  $mix  number of questions per notion key
     * @param  array<string, list<array{0: string, 1: list<string>, 2: int}>>  $bank
     */
    private function createQuiz(User $teacher, string $title, string $subject, Level $level, int $duration, CarbonImmutable $dueAt, CarbonImmutable $correctionsAt, array $targets, array $mix, array &$bank, CarbonImmutable $publishedAt): Quiz
    {
        $quiz = Quiz::create([
            'teacher_id' => $teacher->id, 'title' => $title, 'subject' => $subject, 'level' => $level,
            'description' => 'Quiz de démonstration.', 'duration_minutes' => $duration,
            'due_at' => $dueAt, 'corrections_at' => $correctionsAt, 'published_at' => $publishedAt,
        ]);
        $quiz->groups()->attach(collect($targets)->pluck('id'));

        $position = 0;
        foreach ($mix as $notionKey => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$text, $choices, $correctIndex] = array_shift($bank[$notionKey]);
                $question = Question::create([
                    'quiz_id' => $quiz->id, 'notion_id' => $this->notions[$notionKey]->id,
                    'text' => $text, 'position' => $position++,
                ]);
                foreach ($choices as $p => $choiceText) {
                    $question->choices()->create(['text' => $choiceText, 'is_correct' => $p === $correctIndex, 'position' => $p]);
                }
            }
        }

        return $quiz;
    }

    /**
     * @param  array{td1: Group, td2: Group, l2: Group}  $groups
     * @return array<string, Material>
     */
    private function materials(User $amina, User $omar, array $groups): array
    {
        $disk = Storage::disk(config('focusia.files_disk'));

        $intro = Material::create([
            'teacher_id' => $amina->id, 'title' => 'Cours 1 – Introduction à l\'algorithmique', 'type' => MaterialType::Course,
            'description' => 'Variables, types, conditions et premières boucles.',
            ...$this->storeDemoPdf($disk, 'materials', 'cours-1-introduction-algorithmique.pdf', 'Cours 1 - Introduction a l\'algorithmique'),
        ]);
        $intro->groups()->attach([$groups['td1']->id, $groups['td2']->id]);

        $caseStudy = Material::create([
            'teacher_id' => $omar->id, 'title' => 'Étude de cas – Gestion d\'une bibliothèque universitaire', 'type' => MaterialType::CaseStudy,
            'description' => 'Modéliser emprunts, ouvrages et lecteurs.',
            'url' => 'https://fr.wikipedia.org/wiki/Modèle_relationnel',
            ...$this->storeDemoPdf($disk, 'materials', 'etude-de-cas-bibliotheque.pdf', 'Etude de cas - Gestion d\'une bibliotheque'),
        ]);
        $caseStudy->groups()->attach($groups['l2']->id);

        $sql = Material::create([
            'teacher_id' => $omar->id, 'title' => 'Mémo des requêtes SQL', 'type' => MaterialType::Other,
            'url' => 'https://sql.sh/',
        ]);
        $sql->groups()->attach($groups['l2']->id);

        return ['intro' => $intro, 'case' => $caseStudy, 'sql' => $sql];
    }

    /**
     * Homework covering the 5 states:
     * - Devoir 1 (late allowed, due 2 days ago): RENDU, EN_RETARD, NOTE, A_RENDRE.
     * - Devoir 2 (late refused, due 3 days ago): NOTE, RENDU, NON_RENDU.
     *
     * @param  array{td1: Group, td2: Group, l2: Group}  $groups
     * @param  array<string, Material>  $materials
     */
    private function assignments(User $amina, User $omar, array $groups, array $materials, CarbonImmutable $now): void
    {
        $tri = Assignment::create([
            'teacher_id' => $amina->id, 'material_id' => $materials['intro']->id,
            'title' => 'Devoir 1 – Algorithme de tri par sélection',
            'instructions' => "Écrivez en pseudo-code l'algorithme de tri par sélection, puis déroulez-le sur le tableau [5, 2, 9, 1].",
            'due_at' => $now->subDays(2)->setTime(23, 59), 'allow_late' => true,
        ]);
        $tri->groups()->attach([$groups['td1']->id, $groups['td2']->id]);

        $td1 = $groups['td1']->students()->get()->values();
        foreach ($td1->slice(0, 8) as $i => $student) {
            $late = $i >= 6; // 2 late submissions
            $graded = $i < 4; // 4 graded
            Submission::create([
                'assignment_id' => $tri->id, 'student_id' => $student->id,
                'body' => "Pour i de 0 à n-2 : chercher le minimum entre i et n-1, puis l'échanger avec la case i.",
                'submitted_at' => $late ? $tri->due_at->addHours(10 + $i) : $tri->due_at->subDays(1)->addHours($i),
                'is_late' => $late,
                'grade' => $graded ? [16, 12.5, 9, 14][$i] : null,
                'comment' => $graded ? ['Très bon déroulé.', 'Correct, mais justifiez les échanges.', 'Le déroulé est incomplet.', 'Bien.'][$i] : null,
                'graded_at' => $graded ? $now->subDay() : null,
            ]);
        }

        $modele = Assignment::create([
            'teacher_id' => $omar->id, 'material_id' => $materials['case']->id,
            'title' => 'Devoir 2 – Modèle relationnel de la bibliothèque',
            'instructions' => 'À partir de l\'étude de cas, proposez le schéma relationnel (tables, clés primaires et étrangères).',
            'due_at' => $now->subDays(3)->setTime(18, 0), 'allow_late' => false,
        ]);
        $modele->groups()->attach($groups['l2']->id);

        $disk = Storage::disk(config('focusia.files_disk'));
        foreach ($groups['l2']->students()->get()->values()->slice(0, 6) as $i => $student) {
            $graded = $i < 4;
            Submission::create([
                'assignment_id' => $modele->id, 'student_id' => $student->id,
                'body' => $i % 2 === 0 ? 'Voir le fichier joint.' : 'LIVRE(id, titre, auteur) ; LECTEUR(id, nom) ; EMPRUNT(#livre, #lecteur, date).',
                ...($i % 2 === 0 ? $this->storeDemoPdf($disk, 'submissions', "schema-bibliotheque-{$student->id}.pdf", 'Schema relationnel - '.$student->name) : []),
                'submitted_at' => $modele->due_at->subHours(5 + $i),
                'is_late' => false,
                'grade' => $graded ? [17.5, 11, 8.5, 13][$i] : null,
                'comment' => $graded ? ['Excellent travail.', 'Clés étrangères manquantes.', 'Revoir les cardinalités.', 'Bon schéma.'][$i] : null,
                'graded_at' => $graded ? $now->subDay() : null,
            ]);
        }
        // The remaining 4 students of the L2 group have no submission: NON_RENDU.
    }

    /** @param array{td1: Group, td2: Group, l2: Group} $groups */
    private function resources(User $amina, User $omar, array $groups): void
    {
        $items = [
            [$amina, 'Vidéo – Comprendre les boucles en 10 minutes', ResourceType::Video, 'Algorithmique', Level::L1, 'https://www.youtube.com/results?search_query=boucles+algorithmique', [$groups['td1'], $groups['td2']]],
            [$amina, 'Exercices corrigés – Tableaux', ResourceType::Exercise, 'Algorithmique', Level::L1, 'https://fr.wikibooks.org/wiki/Algorithmique_impérative', [$groups['td1'], $groups['td2']]],
            [$omar, 'Tutoriel SQL interactif', ResourceType::Link, 'Bases de données', Level::L2, 'https://sql.sh/cours', [$groups['l2']]],
            [$omar, 'Normalisation des bases de données', ResourceType::Document, 'Bases de données', Level::L2, 'https://fr.wikipedia.org/wiki/Forme_normale_(bases_de_données_relationnelles)', [$groups['l2']]],
        ];

        foreach ($items as [$teacher, $title, $type, $subject, $level, $url, $targets]) {
            $resource = Resource::create([
                'teacher_id' => $teacher->id, 'title' => $title, 'type' => $type,
                'subject' => $subject, 'level' => $level, 'url' => $url,
            ]);
            $resource->groups()->attach(collect($targets)->pluck('id'));
        }
    }

    /**
     * Writes a tiny valid one-page PDF to the private disk.
     *
     * @return array{file_path: string, file_name: string, mime_type: string, file_size: int}
     */
    private function storeDemoPdf(Filesystem $disk, string $folder, string $fileName, string $title): array
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $title);
        $stream = "BT /F1 18 Tf 72 760 Td ({$text}) Tj 0 -30 Td /F1 12 Tf (Document de demonstration FocusIA) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        $path = $folder.'/demo-'.$fileName;
        $disk->put($path, $pdf);

        return ['file_path' => $path, 'file_name' => $fileName, 'mime_type' => 'application/pdf', 'file_size' => strlen($pdf)];
    }
}

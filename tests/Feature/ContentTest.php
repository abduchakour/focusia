<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Group;
use App\Models\Material;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Phase 6: course materials and library (rules 9, 10, 11, 12, 18, 19, 20) and protected downloads. */
class ContentTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Group $td1;

    private Group $td2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('focusia.files_disk'));

        $this->teacher = User::factory()->teacher()->create();
        $this->td1 = Group::factory()->create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique']);
        $this->td2 = Group::factory()->create(['teacher_id' => $this->teacher->id, 'subject' => 'Algorithmique']);
        $this->student = User::factory()->student()->create();
        $this->td1->enrollments()->create(['student_id' => $this->student->id, 'joined_at' => now()]);
        $this->td2->enrollments()->create(['student_id' => $this->student->id, 'joined_at' => now()]);
    }

    private function pdf(string $name = 'cours.pdf', int $kb = 200): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    /** A real uploaded file: its MIME type is detected from the bytes (finfo), like in production. */
    private function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'focusia');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function storeMaterial(array $data = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->teacher)->post(route('teacher.materials.store'), [
            'title' => 'Cours 1 – Introduction',
            'type' => 'course',
            'file' => $this->pdf(),
            'group_ids' => [$this->td1->id, $this->td2->id],
            ...$data,
        ]);
    }

    private function material(array $attributes = [], ?array $groups = null): Material
    {
        $path = "materials/{$this->teacher->id}/demo.pdf";
        Storage::disk(config('focusia.files_disk'))->put($path, '%PDF-1.4 demo');
        $material = Material::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Cours', 'type' => 'course',
            'file_path' => $path, 'file_name' => 'Cours 1.pdf', 'mime_type' => 'application/pdf', 'file_size' => 13, ...$attributes,
        ]);
        $material->groups()->attach(collect($groups ?? [$this->td1, $this->td2])->pluck('id'));

        return $material;
    }

    // ---------- Creation (rules 10, 11, 20) ----------

    public function test_acceptance_the_teacher_publishes_a_pdf_in_two_groups(): void
    {
        $this->storeMaterial()->assertRedirect(route('teacher.materials.index'))->assertSessionHas('success');

        $material = Material::firstOrFail();
        $this->assertSame('cours.pdf', $material->file_name);
        $this->assertSame('application/pdf', $material->mime_type);
        $this->assertStringStartsWith("materials/{$this->teacher->id}/", $material->file_path);
        $this->assertStringNotContainsString('cours', basename($material->file_path), 'stored under a random name');
        Storage::disk(config('focusia.files_disk'))->assertExists($material->file_path);
        $this->assertSame(2, $material->groups()->count());
    }

    public function test_rule_10_a_link_alone_is_enough_but_nothing_is_refused(): void
    {
        $this->storeMaterial(['file' => null, 'url' => 'https://sql.sh/'])->assertSessionHasNoErrors();
        $this->storeMaterial(['file' => null, 'url' => ''])->assertSessionHasErrors(['file' => 'Ajoutez un fichier, un lien, ou les deux.']);
        $this->storeMaterial(['file' => null, 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->assertSame(1, Material::count());
    }

    public function test_rule_10_the_file_cannot_be_removed_without_a_link(): void
    {
        $material = $this->material();

        $this->actingAs($this->teacher)->put(route('teacher.materials.update', ['material' => $material->id]), [
            'title' => 'Cours', 'type' => 'course', 'remove_file' => true, 'group_ids' => [],
        ])->assertSessionHasErrors('file');
        $this->assertNotNull($material->fresh()->file_path);
    }

    public function test_rule_20_the_real_type_and_the_size_are_checked(): void
    {
        // An executable renamed to .pdf: the content gives it away.
        $this->storeMaterial(['file' => UploadedFile::fake()->create('cours.pdf', 50, 'application/x-msdownload')])
            ->assertSessionHasErrors(['file' => "Ce type de fichier n'est pas accepté. Formats acceptés : PDF, Word, Excel, PowerPoint, ODT, JPG, PNG ou ZIP."]);
        // A real PDF with a forbidden extension.
        $this->storeMaterial(['file' => UploadedFile::fake()->create('cours.exe', 50, 'application/pdf')])->assertSessionHasErrors('file');
        // Too big (10 MB max by default).
        $this->storeMaterial(['file' => $this->pdf('gros.pdf', 10241)])->assertSessionHasErrors(['file' => 'Le fichier dépasse la taille maximale de 10 Mo.']);

        // A Word document renamed to .pdf: accepted extension, but the content does not match it.
        $this->storeMaterial(['file' => UploadedFile::fake()->create('cours.pdf', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Material::count());
    }

    public function test_rule_20_the_type_is_detected_from_the_real_bytes(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n";

        $this->storeMaterial(['file' => $this->realFile('cours.pdf', $png)])->assertSessionHasErrors('file');
        $this->storeMaterial(['file' => $this->realFile('schema.png', $png)])->assertSessionHasNoErrors();
        $this->storeMaterial(['file' => $this->realFile('cours.pdf', $pdf)])->assertSessionHasNoErrors();

        $this->assertSame(['image/png', 'application/pdf'], Material::orderBy('id')->pluck('mime_type')->all());
    }

    public function test_rule_20_every_accepted_format_goes_through(): void
    {
        $formats = [
            'a.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'b.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'c.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'd.odt' => 'application/vnd.oasis.opendocument.text',
            'e.jpg' => 'image/jpeg',
            'f.png' => 'image/png',
            'g.zip' => 'application/zip',
        ];
        foreach ($formats as $name => $mime) {
            $this->storeMaterial(['title' => $name, 'file' => UploadedFile::fake()->create($name, 20, $mime)])->assertSessionHasNoErrors();
        }
        $this->assertSame(count($formats), Material::count());
    }

    public function test_rule_11_the_same_group_twice_or_a_foreign_group_is_refused(): void
    {
        $this->storeMaterial(['group_ids' => [$this->td1->id, $this->td1->id]])->assertSessionHasErrors('group_ids.1');
        $foreign = Group::factory()->create();
        $this->storeMaterial(['group_ids' => [$foreign->id]])->assertSessionHasErrors('group_ids.0');
        $this->assertSame(0, Material::count());
    }

    public function test_replacing_the_file_deletes_the_old_one(): void
    {
        $material = $this->material();
        $old = $material->file_path;

        $this->actingAs($this->teacher)->put(route('teacher.materials.update', ['material' => $material->id]), [
            'title' => 'Cours v2', 'type' => 'course', 'file' => $this->pdf('cours-v2.pdf'), 'group_ids' => [$this->td1->id],
        ])->assertSessionHasNoErrors();

        $material->refresh();
        $this->assertSame('cours-v2.pdf', $material->file_name);
        Storage::disk(config('focusia.files_disk'))->assertMissing($old);
        $this->assertSame([$this->td1->id], $material->groups()->pluck('groups.id')->all());
    }

    // ---------- Ownership (rule 9) and deletion (rule 19) ----------

    public function test_rule_9_another_teacher_cannot_edit_nor_delete(): void
    {
        $material = $this->material();
        $intruder = User::factory()->teacher()->create();

        $this->actingAs($intruder)->get(route('teacher.materials.edit', ['material' => $material->id]))->assertForbidden();
        $this->actingAs($intruder)->put(route('teacher.materials.update', ['material' => $material->id]), ['title' => 'x'])->assertForbidden();
        $this->actingAs($intruder)->delete(route('teacher.materials.destroy', ['material' => $material->id]))->assertForbidden();
        $this->actingAs($intruder)->get(route('files.link', ['kind' => 'support', 'id' => $material->id]))->assertForbidden();
        $this->assertNotNull($material->fresh());
    }

    public function test_rule_19_deleting_a_material_keeps_the_homework(): void
    {
        $material = $this->material();
        $assignment = Assignment::create([
            'teacher_id' => $this->teacher->id, 'material_id' => $material->id,
            'title' => 'Devoir 1', 'instructions' => 'Lire le cours.', 'due_at' => now()->addWeek(),
        ]);

        $this->actingAs($this->teacher)->delete(route('teacher.materials.destroy', ['material' => $material->id]))
            ->assertSessionHas('success', 'Support « Cours » supprimé. Le devoir lié est conservé, sans ce support.');

        $this->assertNull($assignment->fresh()->material_id);
        Storage::disk(config('focusia.files_disk'))->assertMissing($material->file_path);
    }

    // ---------- Student side (rules 12, 18) ----------

    public function test_rule_12_the_student_sees_a_material_once_even_when_published_in_two_groups(): void
    {
        $this->material(['title' => 'Visible']);
        $this->material(['title' => 'Autre groupe'], [Group::factory()->create()]);

        $this->actingAs($this->student)->get(route('student.materials.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('student/content/Index')
                ->has('items', 1)
                ->where('items.0.title', 'Visible')
                ->where('items.0.subject', 'Algorithmique')
                ->where('items.0.teacher', $this->teacher->name)
                ->missing('items.0.file_path'));
    }

    public function test_rule_18_a_removed_student_loses_access(): void
    {
        $material = $this->material(groups: [$this->td1]);
        $this->td1->enrollments()->where('student_id', $this->student->id)->update(['removed_at' => now()]);

        $this->actingAs($this->student)->get(route('student.materials.index'))->assertInertia(fn (Assert $page) => $page->has('items', 0));
        $this->actingAs($this->student)->get(route('files.link', ['kind' => 'support', 'id' => $material->id]))->assertForbidden();
    }

    public function test_the_library_is_classified_and_filtered_by_group(): void
    {
        $resource = Resource::create([
            'teacher_id' => $this->teacher->id, 'title' => 'Vidéo boucles', 'type' => 'video',
            'subject' => 'Algorithmique', 'level' => 'L1', 'url' => 'https://www.youtube.com/watch?v=demo',
        ]);
        $resource->groups()->attach($this->td1->id);

        $this->actingAs($this->student)->get(route('student.resources.index'))
            ->assertInertia(fn (Assert $page) => $page->has('items', 1)->where('items.0.type_label', 'Vidéo')->where('items.0.level', 'L1'));

        $this->actingAs($this->teacher)->post(route('teacher.resources.store'), [
            'title' => 'Exercices', 'type' => 'exercise', 'url' => 'https://exemple.dj', 'group_ids' => [],
        ])->assertSessionHasErrors(['subject' => 'Indiquez la matière.', 'level']);
    }

    // ---------- Protected downloads ----------

    public function test_download_goes_through_a_temporary_signed_link_bound_to_the_user(): void
    {
        $material = $this->material();

        $link = $this->actingAs($this->student)->get(route('files.link', ['kind' => 'support', 'id' => $material->id]))->assertRedirect();
        $signed = $link->headers->get('Location');
        $this->assertStringContainsString('signature=', $signed);
        $this->assertStringContainsString('expires=', $signed);

        $this->actingAs($this->student)->get($signed)
            ->assertOk()
            ->assertDownload('Cours 1.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // The same link does not work for a classmate.
        $classmate = User::factory()->student()->create();
        $this->td1->enrollments()->create(['student_id' => $classmate->id, 'joined_at' => now()]);
        $this->actingAs($classmate)->get($signed)->assertForbidden();
    }

    public function test_an_expired_or_tampered_link_is_refused_in_french(): void
    {
        $material = $this->material();
        $expired = URL::temporarySignedRoute('files.download', now()->subMinute(), ['kind' => 'support', 'id' => $material->id, 'user' => $this->student->id]);

        $this->actingAs($this->student)->get($expired)
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('message', fn ($m) => str_contains($m, 'a expiré')));

        $tampered = str_replace('user='.$this->student->id, 'user=999', URL::temporarySignedRoute(
            'files.download', now()->addMinutes(10), ['kind' => 'support', 'id' => $material->id, 'user' => $this->student->id],
        ));
        $this->actingAs($this->student)->get($tampered)->assertForbidden();
    }

    public function test_files_are_never_served_from_a_public_url(): void
    {
        $material = $this->material();

        $this->get('/storage/'.$material->file_path)->assertNotFound();
        $this->get(route('files.link', ['kind' => 'support', 'id' => $material->id]))->assertRedirect(route('login'));
        $this->actingAs($this->student)->get(route('files.link', ['kind' => 'inconnu', 'id' => 1]))->assertNotFound();
    }
}

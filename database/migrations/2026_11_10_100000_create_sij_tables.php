<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('area_geojson')->nullable();
            $table->string('map_color', 9)->default('#2563eb');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->foreignId('region_id')->nullable()->after('phone')->constrained('regions')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('region_id');
            $table->text('app_authentication_secret')->nullable();
        });

        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->text('address')->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('suggested_region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->unsignedBigInteger('head_member_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('geocode_status', 12)->default('belum'); // belum, tepat, perkiraan, gagal, manual
            $table->timestamp('geocoded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('old_registration_no', 50)->nullable();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->char('gender', 1)->nullable(); // L / P
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('phone', 20)->nullable();
            $table->foreignId('family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->string('family_relation', 20)->nullable(); // kepala, suami, istri, anak, orang_tua, lainnya
            $table->string('membership_status', 20)->default('simpatisan'); // sidi, baptis, anggota, simpatisan, keluar, meninggal
            $table->string('activity_status', 10)->nullable(); // aktif, pasif
            $table->date('marriage_date')->nullable();
            $table->date('death_date')->nullable();
            $table->date('joined_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('alias');
            $table->index(['membership_status', 'activity_status']);
            $table->index('birth_date');
        });

        Schema::table('families', function (Blueprint $table) {
            $table->foreign('head_member_id')->references('id')->on('members')->nullOnDelete();
        });

        Schema::create('member_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('type', 20); // baptis, sidi, nikah, atestasi_masuk, atestasi_keluar, wafat
            $table->date('date')->nullable();
            $table->string('place')->nullable();
            $table->string('church')->nullable();
            $table->string('minister')->nullable();
            $table->string('letter_no')->nullable();
            $table->string('attachment')->nullable();
            $table->string('data_status', 20)->default('lengkap'); // lengkap, belum_diketahui
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'date']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('min_age')->nullable();
            $table->unsignedSmallInteger('max_age')->nullable();
            $table->char('gender', 1)->nullable(); // null = semua
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('schedule')->nullable();
            $table->boolean('count_only')->default(false); // Sekolah Minggu: jumlah total
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('commission_id')->nullable()->after('region_id')->constrained('commissions')->nullOnDelete();
        });

        Schema::create('commission_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained('commissions')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->date('joined_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['commission_id', 'member_id']);
        });

        Schema::create('activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('commission_id')->nullable()->constrained('commissions')->nullOnDelete();
            $table->boolean('is_general_worship')->default(false);
            $table->boolean('count_only')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_type_id')->constrained('activity_types')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('manual_count')->nullable();
            $table->string('qr_token', 64)->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['activity_type_id', 'date']);
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('came_with')->nullable();
            $table->string('source')->nullable();
            $table->string('origin_church')->nullable();
            $table->boolean('consent_contact')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->string('fu_stage', 20)->default('baru'); // baru, dihubungi, datang_lagi, rutin, jemaat, tidak_lanjut
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fu_due_at')->nullable();
            $table->date('first_visit_at')->nullable();
            $table->foreignId('converted_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_session_id')->constrained('activity_sessions')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->cascadeOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained('guests')->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->string('method', 10)->default('petugas'); // petugas, qr
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['activity_session_id', 'member_id']);
            $table->unique(['activity_session_id', 'guest_id']);
        });

        Schema::create('guest_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained('guests')->cascadeOnDelete();
            $table->date('date');
            $table->string('method', 20); // wa, telepon, kunjungan
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->nullable()->constrained('families')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->cascadeOnDelete();
            $table->string('type', 20); // rutin, sakit, duka, ulang_tahun, jemaat_baru, pasif
            $table->date('planned_date');
            $table->date('realized_date')->nullable();
            $table->string('status', 12)->default('rencana'); // rencana, selesai, batal
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'planned_date']);
        });

        Schema::create('visit_user', function (Blueprint $table) {
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->primary(['visit_id', 'user_id']);
        });

        Schema::create('visit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained('visits')->cascadeOnDelete();
            $table->text('attendees')->nullable();
            $table->text('conversation')->nullable();
            $table->text('prayer_points')->nullable();
            $table->text('follow_up')->nullable();
            $table->boolean('is_confidential')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('growth_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('target_new_members');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 20);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'growth_targets', 'visit_reports', 'visit_user', 'visits', 'guest_followups', 'attendances', 'guests',
            'activity_sessions', 'activity_types', 'commission_members'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('commission_id'));
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('member_events');
        Schema::table('families', fn (Blueprint $t) => $t->dropForeign(['head_member_id']));
        Schema::dropIfExists('members');
        Schema::dropIfExists('families');
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('region_id');
            $t->dropColumn(['phone', 'is_active', 'app_authentication_secret']);
        });
        Schema::dropIfExists('regions');
    }
};

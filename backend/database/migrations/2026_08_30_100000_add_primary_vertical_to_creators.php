<?php

use App\Services\Discovery\CanonicalCreatorVerticals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `creators.niche` is a human label. Discovery writes whatever the model read off
 * the account ("Entrepreneurship / SaaS"), the catalog importer writes its own,
 * and registered creators got a canonical slug — three vocabularies in one
 * column. The feed compares verticals, so it needs the canonical one stored next
 * to the label rather than re-derived from text in every query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creators', function (Blueprint $table): void {
            $table->string('primary_vertical')->nullable()->index()->after('niche_topics');
        });

        $verticals = new CanonicalCreatorVerticals;

        DB::table('creators')
            ->select(['id', 'niche', 'niche_topics', 'bio'])
            ->orderBy('id')
            ->chunk(500, function ($creators) use ($verticals): void {
                foreach ($creators as $creator) {
                    $topics = json_decode((string) $creator->niche_topics, true);

                    $vertical = $verticals->fromSignals([
                        $creator->niche,
                        ...(is_array($topics) ? $topics : []),
                        $creator->bio,
                    ]);

                    if ($vertical !== null) {
                        DB::table('creators')->where('id', $creator->id)->update(['primary_vertical' => $vertical]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('creators', function (Blueprint $table): void {
            $table->dropColumn('primary_vertical');
        });
    }
};

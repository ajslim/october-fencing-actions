<?php namespace Ajslim\FencingActions\Updates;

use Illuminate\Support\Facades\DB;
use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateActionsTable extends Migration
{
    /**
     * Creates tables
     *
     * @return null
     */
    public function up()
    {
        Schema::connection('business')->create(
            'ajslim_fencingactions_actions', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->string('video_url', 300)
                    ->nullable();
                $table->string('thumb_url', 300)
                    ->nullable();
                $table->integer('time')
                    ->nullable();

                // 1 is left, 2 is right
                $table->integer('priority')
                    ->nullable();

                $table->integer('call_on_site_id')
                    ->nullable() // To allow deferred bindings
                    ->unsigned();
                $table->foreign('call_on_site_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_calls');


                // Actions either have a bout
                // Or a tournament and left and right fencers
                $table->integer('bout_id')
                    ->nullable() // To allow deferred bindings
                    ->unsigned();
                $table->foreign('bout_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_bouts');


                $table->integer('left_fencer_id')
                    ->nullable() // To allow deferred bindings
                    ->unsigned();
                $table->foreign('left_fencer_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_fencers');

                $table->integer('right_fencer_id')
                    ->nullable() // To allow deferred bindings
                    ->unsigned();
                $table->foreign('right_fencer_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_fencers');

                $table->integer('tournament_id')
                    ->nullable() // To allow deferred bindings
                    ->unsigned();
                $table->foreign('tournament_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_tournaments');


                // Cache of calculated values
                $table->integer('vote_count_cache')
                    ->nullable();

                $table->string('top_vote_name_cache')
                    ->nullable();

                $table->float('confidence_cache')
                    ->nullable();

                $table->float('consensus_cache')
                    ->nullable();

                $table->float('average_difficulty_cache')
                    ->nullable();

                $table->timestamps();
            }
        );
    }

    /**
     * Removes tables
     *
     * @return null
     */
    public function down()
    {
        Schema::connection('business')->dropIfExists('ajslim_fencingactions_actions');
    }
}

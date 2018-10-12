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
                $table->string('gfycat_id', 100);
                $table->integer('priority');

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

                $table->integer('call_on_site_id')
                    ->nullable() // To allow deferred bindings
                    ->unsigned();
                $table->foreign('call_on_site_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_calls');

                $table->integer('tournament_id')
                    ->nullable() // To allow deferred bindings
                    ->unsigned();
                $table->foreign('tournament_id')
                    ->references('id')
                    ->on('ajslim_fencingactions_tournaments');

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

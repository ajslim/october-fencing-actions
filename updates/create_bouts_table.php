<?php namespace Ajslim\FencingActions\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateBoutsTable extends Migration
{
    public function up()
    {

        Schema::connection('business')->create('ajslim_fencingactions_bouts', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('cache_name')
                ->nullable(); // A place to cache the bout name to speed up lookups
            $table->string('video_url')
                ->nullable(); // A place to cache the bout name to speed up lookups
            $table->integer('left_score');
            $table->integer('right_score');

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

            // If the fencers are reversed the date is logged so you can match against warnings
            $table->timestamp('fencers_reversed')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('business')->dropIfExists('ajslim_fencingactions_bouts');
    }
}

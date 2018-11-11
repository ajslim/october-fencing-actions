<?php namespace Ajslim\Fencingactions\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateVotesTable extends Migration
{
    public function up()
    {
        Schema::connection('business')->create('ajslim_fencingactions_votes', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('action_id')
                ->nullable();
            $table->integer('call_id')
                ->nullable();
            $table->integer('priority')
                ->nullable();
            $table->integer('difficulty')
                ->nullable();
            $table->integer('vote_comment_id')
                ->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ajslim_fencingactions_votes');
    }
}

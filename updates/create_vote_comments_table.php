<?php namespace Ajslim\Fencingactions\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

class CreateVoteCommentsTable extends Migration
{
    public function up()
    {
        Schema::connection('business')->create('ajslim_fencingactions_vote_comments', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name', 100);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ajslim_fencingactions_votes');
    }
}

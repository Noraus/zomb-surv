<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$world=App\Models\World::where('dataset','demo')->firstOrFail();
$service=app(App\Services\WorldService::class);
$state=$service->state($world);
$state['world']['minutes']=480;
$state['player']->fill(['location_id'=>null,'lat'=>43.3183,'lon'=>-1.9812,'health'=>100,'energy'=>100,'hunger'=>15,'thirst'=>10,'survived'=>0]);
$state['inventory']=[];$state['weight']=0;$state['visited']=0;
$places=App\Models\WorldLocation::where('world_id',$world->id)->get()->map(function($place)use($world){
 $place->visits=0;$place->last_visit=null;
 return ['location'=>$place,'stock'=>app(App\Services\LootGenerator::class)->generate($place->stable_id,$place->category,$world->seed,$place->generation_context??[])];
});
echo json_encode(['state'=>$state,'places'=>$places,'catalog'=>App\Services\LootGenerator::catalog(),'categories'=>App\Services\Classification::categories()],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);

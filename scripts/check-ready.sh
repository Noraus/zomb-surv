#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
node --input-type=module <<'JS'
let last;
for(let attempt=0;attempt<40;attempt++){
 try{
 const r=await fetch('http://127.0.0.1:8000/api/v1/world');if(!r.ok)throw Error('API '+r.status);const w=await r.json();if(!w.player||!w.categories||w.categories.length<18)throw Error('Incomplete world');
 const places=await fetch('http://127.0.0.1:8000/api/v1/locations?dataset=demo').then(r=>r.json());if(places.features.length<18)throw Error('Demo missing');console.log('PASS: API, persistent player, 18 categories and demo locations.');process.exit(0);
 }catch(e){last=e;await new Promise(resolve=>setTimeout(resolve,500));}
}
throw last;
JS

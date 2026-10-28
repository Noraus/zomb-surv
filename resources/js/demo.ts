import seed from './demo-data.json';
const KEY='ultima-senal-pages-v1';
type Save={version:1;state:any;places:any[];inventory:Record<string,number>;events:any[];nextEvent:number};
const catalog=seed.catalog as any[];
const clone=<T,>(v:T):T=>structuredClone(v);
function initial():Save{return {version:1,state:clone(seed.state),places:clone(seed.places),inventory:{},events:[],nextEvent:1};}
function read():Save{const raw=localStorage.getItem(KEY);if(!raw)return initial();try{const data=JSON.parse(raw);if(data.version!==1||!data.state?.player||!Array.isArray(data.places))throw Error();return data;}catch{throw Error('No se puede leer la partida local. Usa Desarrollo → Reiniciar demostración para empezar de nuevo.');}}
function weight(s:Save){return catalog.reduce((n,r)=>n+(s.inventory[r.id]||0)*Number(r.weight),0);}
function state(s:Save){return {...clone(s.state),inventory:catalog.filter(r=>(s.inventory[r.id]||0)>0).map(r=>({...r,quantity:s.inventory[r.id]})),weight:Math.round(weight(s)*1000)/1000,visited:s.places.filter(p=>p.location.visits>0).length,categories:seed.categories,map_style:'https://tiles.openfreemap.org/styles/liberty',development:true,standalone:true,parameters:{travel_multiplier:1.3}};}
function distance(a:number,b:number,c:number,d:number){const rad=Math.PI/180,x=Math.sin((c-a)*rad/2)**2+Math.cos(a*rad)*Math.cos(c*rad)*Math.sin((d-b)*rad/2)**2;return 6371*2*Math.atan2(Math.sqrt(x),Math.sqrt(Math.max(0,1-x)));}
function tick(s:Save,minutes:number,energy:number){const p=s.state.player;s.state.world.minutes+=minutes;p.survived+=minutes;p.energy=Math.min(100,Math.max(0,p.energy-energy));p.hunger=Math.min(100,p.hunger+Math.max(1,Math.ceil(minutes/15)));p.thirst=Math.min(100,p.thirst+Math.max(1,Math.ceil(minutes/10)));if(p.hunger===100||p.thirst===100)p.health=Math.max(1,p.health-3);}
async function execute(path:string,body?:any){
 if(path==='dev/reset'&&body!==undefined){const fresh=initial();localStorage.setItem(KEY,JSON.stringify(fresh));return {message:'Demostración local reiniciada.'};}
 const s=read();const url=new URL(path,'https://demo.invalid/');
 if(url.searchParams.get('dataset')==='osm'||body?.dataset==='osm')throw Error('La demo de GitHub Pages utiliza solo lugares ficticios. La importación OSM requiere la aplicación Laravel.');
 const route=url.pathname.slice(1);
 if(route==='world'||route==='player'||route==='inventory')return state(s);
 if(route==='categories')return clone(seed.categories);
 if(route==='dev/import')throw Error('Overpass está disponible en la aplicación Laravel. Esta demo funciona sin backend y utiliza lugares ficticios.');
 if(route==='dev/status'){const counts=seed.categories.map(c=>({category:c.id,source:'simulated',total:s.places.filter(p=>p.location.category===c.id).length}));return {seed:s.state.world.seed,minutes:s.state.world.minutes,counts,resources:s.places.reduce((n,p)=>n+Object.values(p.stock).reduce<number>((a,v)=>a+Number(v),0),0),imports:[]};}
 if(route==='locations'||route==='zones'||route==='statistics'){
  const category=url.searchParams.get('category'),q=(url.searchParams.get('q')||'').toLocaleLowerCase('es');const bounds=url.searchParams.get('bbox')?.split(',').map(Number);
  const places=s.places.filter(({location:l})=>(!category||l.category===category)&&l.name.toLocaleLowerCase('es').includes(q)&&(!bounds||(l.lon>=bounds[0]&&l.lat>=bounds[1]&&l.lon<=bounds[2]&&l.lat<=bounds[3]))&&(route!=='zones'||l.kind==='zone'));
  const statistics:Record<string,number>={};for(const {location:l} of places)statistics[l.category]=(statistics[l.category]||0)+1;
  return {type:'FeatureCollection',features:places.map(({location:l})=>({type:'Feature',id:l.id,geometry:{type:'Point',coordinates:[l.lon,l.lat]},properties:{...l,color:seed.categories.find(c=>c.id===l.category)?.color||'#aaa'}})),statistics,total:places.length,limited:false};
 }
 if(/^locations\/\d+$/.test(route)){
  const place=s.places.find(p=>p.location.id===Number(route.split('/')[1]));if(!place)throw Error('Lugar no encontrado.');const l=place.location;
  return {location:clone(l),probable:catalog.filter(r=>r.id in place.stock),stock:l.visits?Object.entries(place.stock).map(([resource_id,quantity])=>({resource_id,quantity,name:catalog.find(r=>r.id===resource_id)?.name})):null,history:s.events.filter(e=>e.location_id===l.id).slice(-8).reverse(),scarcity:l.visits?'Explorado · existencias limitadas':'Desconocida'};
 }
 if(!route.startsWith('actions/')||body===undefined)throw Error('Operación no disponible en la demo.');
 const action=route.slice(8),p=s.state.player;const target=s.places.find(v=>v.location.id===body.location_id),l=target?.location;
 const previous={health:p.health,energy:p.energy,minutes:s.state.world.minutes};const result:any={title:'Acción completada',minutes:0,energy:0,health:0,found:[],risk:'Sin incidentes'};
 if(action==='travel'){
  if(!l||l.kind!=='poi')throw Error('Selecciona una localización visitable.');const km=distance(p.lat,p.lon,l.lat,l.lon),minutes=Math.max(1,Math.ceil(km*1.3/4*60)),cost=Math.max(1,Math.ceil(minutes*.45));if(p.energy<cost)throw Error('Energía insuficiente. Descansa antes de viajar.');
  p.lat=l.lat;p.lon=l.lon;p.location_id=l.id;tick(s,minutes,cost);Object.assign(result,{title:'Destino alcanzado',distance_km:km});
 }else if(action==='scavenge'){
  if(!l||p.location_id!==l.id||distance(p.lat,p.lon,l.lat,l.lon)>.03)throw Error('Debes viajar hasta esta localización antes de registrarla.');if(p.energy<8)throw Error('Necesitas al menos 8 puntos de energía.');let free=p.capacity-weight(s);if(free<.1)throw Error('La mochila está llena. Consume alimentos o agua.');
  let remaining=0;for(const [id,available] of Object.entries(target.stock)){remaining+=Number(available);const r=catalog.find(r=>r.id===id);const count=Math.min(Number(available),2,Math.floor((free+.00001)/r.weight));if(count<1)continue;target.stock[id]-=count;s.inventory[id]=(s.inventory[id]||0)+count;free-=count*r.weight;result.found.push({resource_id:id,name:r.name,quantity:count});if(result.found.length>=3)break;}
  if(remaining>0&&!result.found.length)throw Error('No queda capacidad suficiente para estos objetos.');
  const digest=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(`${s.state.world.seed}:${l.stable_id}:${l.visits}`));const bytes=new Uint8Array(digest),roll=((bytes[0]<<8)|bytes[1])%100;const damage=roll<Math.min(90,l.risk+Math.min(20,l.visits*3))?3:0;p.health=Math.max(1,p.health-damage);l.visits++;tick(s,15,8+(damage?4:0));l.last_visit=s.state.world.minutes;
  Object.assign(result,{title:'Expedición completada',risk:damage?'Infectados cerca: herida leve y esfuerzo adicional.':'Sin incidentes',remaining:Object.values(target.stock).reduce<number>((a,v)=>a+Number(v),0)});
 }else if(action==='rest'){tick(s,60,-35);result.title='Descanso completado';
 }else if(action==='consume'){
  const r=catalog.find(r=>r.id===body.resource_id);if(!r||!(s.inventory[r.id]>0))throw Error('No tienes ese recurso.');if(!r.properties.food&&!r.properties.water)throw Error('Este objeto no se puede consumir.');s.inventory[r.id]--;p.hunger=Math.max(0,p.hunger-r.properties.food);p.thirst=Math.max(0,p.thirst-r.properties.water);result.title='Consumido: '+r.name;
 }else throw Error('Acción desconocida.');
 Object.assign(result,{minutes:s.state.world.minutes-previous.minutes,energy:previous.energy-p.energy,health:p.health-previous.health});const event={id:s.nextEvent++,world_id:s.state.world.id,type:action,minute:s.state.world.minutes,location_id:l?.id??null,result};s.events.push(event);localStorage.setItem(KEY,JSON.stringify(s));return {event,...state(s)};
}
// Web Locks serializes read-modify-write across tabs on HTTPS (GitHub Pages).
let queue=Promise.resolve();
export function demoRequest(path:string,body?:unknown):Promise<any>{
 const run=()=>navigator.locks?navigator.locks.request(KEY,()=>execute(path,body)):execute(path,body);
 const next=queue.then(run,run);queue=next.then(()=>undefined,()=>undefined);return next;
}

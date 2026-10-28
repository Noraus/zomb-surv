import {useEffect,useRef,useState} from 'react';
import maplibregl,{type Map as GLMap} from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import type {Collection,State} from './types';
export default function WorldMap({data,state,selected,onSelect,onBounds}:{data:Collection;state:State;selected:number|null;onSelect:(id:number)=>void;onBounds:(bbox:string)=>void}){
 const container=useRef<HTMLDivElement>(null),map=useRef<GLMap|null>(null);const [offline,setOffline]=useState(false);const callbacks=useRef({onSelect,onBounds});callbacks.current={onSelect,onBounds};
 const latest=useRef({data,state,selected});latest.current={data,state,selected};
 const sync=()=>{const m=map.current;if(!m?.isStyleLoaded())return;const x=latest.current;
  const points={type:'FeatureCollection',features:x.data.features.filter(f=>f.geometry.type==='Point')};
  const zones={type:'FeatureCollection',features:x.data.features.filter(f=>f.geometry.type!=='Point')};
  for(const [id,geo] of [['places',points],['zones',zones],['survivor',{type:'FeatureCollection',features:[{type:'Feature',geometry:{type:'Point',coordinates:[x.state.player.lon,x.state.player.lat]},properties:{}}]}]] as const){(m.getSource(id) as maplibregl.GeoJSONSource|undefined)?.setData(geo as any);}
  m.setPaintProperty('places-dot','circle-radius',['case',['==',['get','id'],x.selected??-1],15,9]);
 };
 useEffect(()=>{if(!container.current)return;const m=new maplibregl.Map({container:container.current,style:state.map_style,center:[-1.9812,43.3183],zoom:13.3,maxZoom:18,attributionControl:{compact:true,customAttribution:'© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> · <a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a>'}});map.current=m;
 m.addControl(new maplibregl.NavigationControl({showCompass:false}),'bottom-right');
 let fallback=false;const timer=window.setTimeout(()=>{if(!m.isStyleLoaded())useFallback();},12000);
 function useFallback(){if(fallback)return;fallback=true;setOffline(true);m.setStyle({version:8,sources:{},layers:[{id:'background',type:'background',paint:{'background-color':'#19241f'}}]});}
 m.on('error',e=>{if(!m.isStyleLoaded() && /style|fetch|403|Failed/i.test(e.error.message))useFallback();});
 m.on('style.load',()=>{clearTimeout(timer);
 m.addSource('places',{type:'geojson',data:{type:'FeatureCollection',features:[]},cluster:true,clusterMaxZoom:13,clusterRadius:38});
 m.addSource('zones',{type:'geojson',data:{type:'FeatureCollection',features:[]}});
 m.addSource('survivor',{type:'geojson',data:{type:'FeatureCollection',features:[]}});
 m.addLayer({id:'zone-fill',type:'fill',source:'zones',filter:['in',['geometry-type'],['literal',['Polygon','MultiPolygon']]],paint:{'fill-color':['get','color'],'fill-opacity':.22}});
 m.addLayer({id:'zone-line',type:'line',source:'zones',paint:{'line-color':['get','color'],'line-width':2,'line-opacity':.6}});
 m.addLayer({id:'clusters',type:'circle',source:'places',filter:['has','point_count'],paint:{'circle-color':'#b9cb8c','circle-radius':19,'circle-stroke-width':3,'circle-stroke-color':'#24352c'}});
 m.addLayer({id:'cluster-count',type:'symbol',source:'places',filter:['has','point_count'],layout:{'text-field':['get','point_count_abbreviated'],'text-size':12}});
 m.addLayer({id:'places-dot',type:'circle',source:'places',filter:['!', ['has','point_count']],paint:{'circle-color':['get','color'],'circle-radius':6,'circle-stroke-width':3,'circle-stroke-color':'#16221b'}});
 for(const category of latest.current.state.categories){
  const canvas=document.createElement('canvas');canvas.width=40;canvas.height=40;const ctx=canvas.getContext('2d')!;
  ctx.fillStyle='#17251c';ctx.beginPath();ctx.arc(20,20,16,0,Math.PI*2);ctx.fill();ctx.strokeStyle=category.color;ctx.lineWidth=2;ctx.stroke();ctx.fillStyle=category.color;ctx.textAlign='center';ctx.textBaseline='middle';ctx.font='bold 23px system-ui';
  const glyph:Record<string,string>={medical:'+',food:'≡',tools:'⚒',fuel:'◆',combat:'!',nature:'♧',materials:'▤',equipment:'◇'};ctx.fillText(glyph[category.group]??'•',20,20);
  m.addImage('category-'+category.id,ctx.getImageData(0,0,40,40));
 }
 m.addLayer({id:'place-icons',type:'symbol',source:'places',filter:['!', ['has','point_count']],layout:{'icon-image':['concat','category-',['get','category']],'icon-size':.7,'icon-allow-overlap':true}});
 m.on('click','place-icons',e=>{const id=e.features?.[0]?.properties?.id;if(id)callbacks.current.onSelect(Number(id));});
 m.addLayer({id:'survivor-halo',type:'circle',source:'survivor',paint:{'circle-radius':17,'circle-color':'#ddf5ac','circle-opacity':.15}});
 m.addLayer({id:'survivor-dot',type:'circle',source:'survivor',paint:{'circle-radius':6,'circle-color':'#e6f4bc','circle-stroke-width':3,'circle-stroke-color':'#fff'}});
 sync();callbacks.current.onBounds(m.getBounds().toArray().flat().join(','));
 });
 m.on('click','places-dot',e=>{const id=e.features?.[0]?.properties?.id;if(id)callbacks.current.onSelect(Number(id));});
 m.on('click','zone-fill',e=>{const id=e.features?.[0]?.properties?.id;if(id)callbacks.current.onSelect(Number(id));});
 m.on('click','clusters',async e=>{const f=e.features?.[0];if(!f)return;const zoom=await (m.getSource('places') as maplibregl.GeoJSONSource).getClusterExpansionZoom(Number(f.properties?.cluster_id));m.easeTo({center:(f.geometry as any).coordinates,zoom});});
 m.on('mouseenter','places-dot',()=>m.getCanvas().style.cursor='pointer');m.on('mouseleave','places-dot',()=>m.getCanvas().style.cursor='');
 m.on('moveend',()=>callbacks.current.onBounds(m.getBounds().toArray().flat().join(',')));
 return()=>{clearTimeout(timer);m.remove();map.current=null;};
 },[state.map_style]);
 useEffect(sync,[data,state.player.lon,state.player.lat,selected]);
 useEffect(()=>{map.current?.easeTo({center:[state.player.lon,state.player.lat],duration:1100});},[state.player.lon,state.player.lat]);
 return <><div ref={container} className="world-map"/><div className="map-attribution">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> · <a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a></div>{offline&&<div className="map-warning">Mapa base sin conexión · los lugares y la simulación siguen disponibles</div>}<button className="recenter icon-button" title="Centrar superviviente" onClick={()=>map.current?.flyTo({center:[state.player.lon,state.player.lat],zoom:15})}>⌖</button></>;
}

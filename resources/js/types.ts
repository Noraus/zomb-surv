export type Category={id:string;name:string;color:string;group:string};
export type Place={id:number;name:string;category:string;kind:string;source:string;risk:number;visits:number;lat:number;lon:number;stable_id:string;rule:string;confidence:number;osm_id?:number;osm_type?:string;tags:Record<string,string>;geometry:unknown;last_visit:number|null};
export type Item={id:string;name:string;weight:number;quantity:number;group:string;properties:{food:number;water:number}};
export type Event={id:number;type:string;minute:number;result:{title:string;minutes:number;energy:number;health:number;found:{name:string;quantity:number}[];risk:string;remaining?:number}};
export type State={standalone?:boolean;world:{id:number;dataset:string;seed:string;minutes:number};player:{id:number;name:string;lat:number;lon:number;location_id:number|null;health:number;energy:number;hunger:number;thirst:number;capacity:number;survived:number};inventory:Item[];weight:number;visited:number;categories:Category[];map_style:string;development:boolean;parameters:{travel_multiplier:number}};
export type Detail={location:Place;probable:Item[];stock:{resource_id:string;name:string;quantity:number}[]|null;history:Event[];scarcity:string};
export type Collection={type:'FeatureCollection';features:any[];statistics:Record<string,number>;total:number;limited:boolean};

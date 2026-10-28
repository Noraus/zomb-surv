import{test,expect}from'@playwright/test';
for(const viewport of [{width:1440,height:1000},{width:375,height:812},{width:390,height:844}]){
 test(`demo autónoma persistente ${viewport.width}`,async({page})=>{
 await page.setViewportSize(viewport);const apiRequests:string[]=[];page.on('request',r=>{if(r.url().includes('/api/v1/'))apiRequests.push(r.url())});
 await page.goto('./');await expect(page.getByText('DEMO LOCAL / NAVEGADOR')).toBeVisible();
 await page.getByRole('button',{name:'Lugares',exact:true}).click();await page.getByRole('button',{name:/Supermercado · Sector/}).click();await page.getByRole('button',{name:'Viajar a esta localización'}).click();await expect(page.getByRole('dialog')).toContainText('Destino alcanzado');await page.getByRole('button',{name:'Continuar exploración'}).click();
 await page.getByRole('button',{name:'Registrar localización'}).click();await expect(page.getByRole('dialog')).toContainText('Agua embotellada');await page.getByRole('button',{name:'Continuar exploración'}).click();
 const saved=await page.evaluate(()=>localStorage.getItem('ultima-senal-pages-v1'));expect(saved).toBeTruthy();await page.reload();await page.getByRole('button',{name:'Inventario',exact:true}).click();await expect(page.locator('.inventory-list')).toContainText('Agua embotellada');
 await page.locator('.inventory-item').filter({hasText:'Agua embotellada'}).getByRole('button',{name:'Consumir'}).click();await expect(page.getByRole('dialog')).toContainText('Consumido: Agua embotellada');await page.getByRole('button',{name:'Continuar exploración'}).click();
 const current=await page.evaluate(()=>JSON.parse(localStorage.getItem('ultima-senal-pages-v1')!));expect(current.inventory.water).toBe(1);expect(current.places.find((p:any)=>p.location.category==='supermarket').location.visits).toBe(1);expect(apiRequests).toEqual([]);expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();
 await page.screenshot({path:`/tmp/pages-demo-${viewport.width}.png`,animations:'disabled'});
 await page.getByRole('button',{name:'Desarrollo',exact:true}).click();page.once('dialog',d=>d.accept());await page.getByRole('button',{name:'Reiniciar solo demostración'}).click();await expect.poll(()=>page.evaluate(()=>JSON.parse(localStorage.getItem('ultima-senal-pages-v1')!).events.length)).toBe(0);
 });
}

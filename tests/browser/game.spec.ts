import {test,expect} from '@playwright/test';
for(const viewport of [{width:1440,height:1000},{width:375,height:812},{width:390,height:844}]){
 test(`viajar, saquear y persistir ${viewport.width}`,async({page,request})=>{
 await page.setViewportSize(viewport);await request.post('/api/v1/dev/reset');
 const errors:string[]=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('/');await expect(page.getByRole('heading',{name:'ÚLTIMA SEÑAL'})).toBeVisible();
 await page.getByRole('button',{name:'Lugares',exact:true}).click();await page.getByRole('button',{name:/Supermercado · Sector/}).click();
 await page.getByRole('button',{name:'Viajar a esta localización'}).click();await expect(page.getByRole('dialog')).toContainText('Destino alcanzado');await page.getByRole('button',{name:'Continuar exploración'}).click();
 await page.getByRole('button',{name:'Registrar localización'}).click();await expect(page.getByRole('dialog')).toContainText('Expedición completada');await expect(page.getByRole('dialog')).toContainText('Agua embotellada');
 await page.getByRole('button',{name:'Continuar exploración'}).click();await page.getByRole('button',{name:'Inventario',exact:true}).click();await expect(page.getByRole('heading',{name:'Tu mochila'})).toBeVisible();await expect(page.locator('.inventory-list')).toContainText('Agua embotellada');
 await page.reload();await page.getByRole('button',{name:'Inventario',exact:true}).click();await expect(page.locator('.inventory-list')).toContainText('Agua embotellada');
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBeTruthy();expect(errors).toEqual([]);
 await page.screenshot({path:`/tmp/survival-${viewport.width}.png`,fullPage:true,animations:'disabled'});
 });
}

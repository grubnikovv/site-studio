const {chromium}=require('playwright');
(async()=>{const b=await chromium.launch();const p=await b.newPage({viewport:{width:1500,height:1000},deviceScaleFactor:2});
for(const n of ['menu','catalog','book']){await p.goto('file://'+__dirname+'/'+n+'.html');await p.waitForTimeout(300);await p.screenshot({path:__dirname+'/'+n+'.png'});}
await b.close();})();

import http from 'node:http';import fs from 'node:fs';import path from 'node:path';
const root='/Users/anisse/Desktop/COC_TRACKER/public';
const list=[...fs.readdirSync(root+'/img/troupes').filter(f=>f.endsWith('.webp')).sort().map(f=>({key:f.replace('.webp',''),src:'/img/troupes/'+f})),
...[10,11,12,13,14,15,16,17,18,19].map(n=>({key:'th'+n,src:'/img/th/th'+n+'.png'}))];
fs.writeFileSync('/private/tmp/claude-501/-Users-anisse-Desktop-COC-TRACKER/00cb82fd-9bdf-4c1e-9859-7b4da06a9574/scratchpad/sp/keys.json',JSON.stringify(list.map(l=>l.key)));
http.createServer((q,s)=>{
 if(q.url==='/list.json'){s.setHeader('content-type','application/json');return s.end(JSON.stringify(list));}
 if(q.url==='/'){s.setHeader('content-type','text/html');return s.end(fs.readFileSync(new URL('./page.html',import.meta.url)));}
 const f=path.join(root,q.url);try{s.end(fs.readFileSync(f))}catch{s.statusCode=404;s.end()}
}).listen(8765);

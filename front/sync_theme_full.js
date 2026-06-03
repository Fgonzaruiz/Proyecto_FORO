const fs = require('fs');
const path = require('path');

const ROOT = __dirname;
const XML_PATH = path.join(ROOT, "Default-theme.xml");
const MANIFEST_PATH = path.join(ROOT, "theme_templates.json");
const RPG_CSS_PATH = path.join(ROOT, "..", "back", "forum", "rpg_custom.css");
const MINIMAL_CSS_PATH = path.join(ROOT, "templates", "mybb", "global", "mybb-minimal.css");

if (!fs.existsSync(XML_PATH)) {
    console.error("Default-theme.xml not found.");
    process.exit(1);
}

let xml = fs.readFileSync(XML_PATH, 'utf8');
const manifest = JSON.parse(fs.readFileSync(MANIFEST_PATH, 'utf8'));

for (const [name, rel] of Object.entries(manifest)) {
    const filePath = path.join(ROOT, rel.replace(/\//g, path.sep));
    if (!fs.existsSync(filePath)) {
        console.log(`SKIP ${name} (missing ${rel})`);
        continue;
    }
    let html = fs.readFileSync(filePath, 'utf8').replace(/\r\n/g, "\n");
    // Escape special regex characters in the template name
    const escapedName = name.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
    const pattern = new RegExp(`(<template\\s+name="${escapedName}"[^>]*><!\\[CDATA\\[)(.*?)(\\]\\]><\\/template>)`, 'gs');
    
    if (!pattern.test(xml)) {
        console.log(`WARN ${name} not in XML`);
        continue;
    }
    
    xml = xml.replace(pattern, (match, p1, p2, p3) => {
        return p1 + html + "\n\t\t" + p3;
    });
    console.log(`OK ${name}`);
}

if (fs.existsSync(RPG_CSS_PATH)) {
    const css = fs.readFileSync(RPG_CSS_PATH, 'utf8').replace(/\r\n/g, "\n");
    const marker = "/* RPG Premium Modern Theme */";
    const cssPattern = /(<stylesheet\s+name="global\.css"[^>]*><!\[CDATA\[)(.*?)(<\/stylesheet>)/gs;
    
    let baseCss = "";
    if (fs.existsSync(MINIMAL_CSS_PATH)) {
        baseCss = fs.readFileSync(MINIMAL_CSS_PATH, 'utf8').replace(/\r\n/g, "\n");
        if (baseCss && !baseCss.endsWith("\n")) {
            baseCss += "\n";
        }
    }
    
    xml = xml.replace(cssPattern, (match, p1, p2, p3) => {
        return p1 + baseCss + marker + "\n" + css + "]]>\n\t\t" + p3;
    });
    console.log("OK global.css");
}

fs.writeFileSync(XML_PATH, xml, 'utf8');
console.log("Default-theme.xml updated successfully.");

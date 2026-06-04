const fs = require('fs');
const readline = require('readline');

const logFile = "C:\\Users\\Fgonz\\.gemini\\antigravity\\brain\\fcf7f171-2f30-45a9-82e4-2a6492a9dfff\\.system_generated\\logs\\transcript.jsonl";

async function run() {
    const fileStream = fs.createReadStream(logFile);
    const rl = readline.createInterface({
        input: fileStream,
        crlfDelay: Infinity
    });

    for await (const line of rl) {
        try {
            const data = JSON.parse(line);
            if (data.step_index === 1174 || data.step_index === 1175) {
                console.log(`Step ${data.step_index}:`);
                console.log(JSON.stringify(data, null, 2));
                console.log("------------------------");
            }
        } catch (e) {
        }
    }
}

run();

const fs = require('fs');
const readline = require('readline');
const path = require('path');

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
            if (data.tool_calls) {
                for (const call of data.tool_calls) {
                    if (call.name === 'run_command' && call.args && call.args.CommandLine) {
                        console.log(`Step ${data.step_index || '?'}: ${call.args.CommandLine}`);
                    }
                }
            }
        } catch (e) {
            // Ignore parse errors
        }
    }
}

run();

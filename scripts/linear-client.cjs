#!/usr/bin/env node
/**
 * Linear CLI helper for UNB Wire (unb-wire-ngx)
 * Reads key from .secrets/linear-api-key
 */
const https = require('https');
const fs = require('fs');
const path = require('path');

const keyPath = path.resolve(__dirname, '../.secrets/linear-api-key');
let apiKey = process.env.LINEAR_API_KEY;

if (!apiKey && fs.existsSync(keyPath)) {
  apiKey = fs.readFileSync(keyPath, 'utf8').trim();
}

if (!apiKey) {
  console.error('Error: Linear API key not found in .secrets/linear-api-key or LINEAR_API_KEY env.');
  process.exit(1);
}

const PROJECT_ID = '5202a7a5-f17d-4b7d-aa28-a6871f9016f6'; // unb-wire-ngx

function gql(query, variables = {}) {
  return new Promise((resolve, reject) => {
    const payload = JSON.stringify({ query, variables });
    const req = https.request('https://api.linear.app/graphql', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': apiKey,
      },
    }, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        try {
          const parsed = JSON.parse(body);
          if (parsed.errors) {
            reject(new Error(JSON.stringify(parsed.errors)));
          } else {
            resolve(parsed.data);
          }
        } catch (e) {
          reject(new Error(body));
        }
      });
    });
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

async function listIssues(filter = 'active') {
  const query = `
    query {
      project(id: "${PROJECT_ID}") {
        id
        name
        issues(first: 100) {
          nodes {
            id
            identifier
            title
            priority
            state { id name type }
            assignee { id name }
            labels { nodes { name } }
          }
        }
      }
    }
  `;
  const data = await gql(query);
  let issues = data.project.issues.nodes;
  if (filter === 'active') {
    issues = issues.filter(i => !['completed', 'canceled'].includes(i.state.type));
  }
  return issues;
}

async function getIssue(identifier) {
  const query = `
    query($id: String!) {
      issue(id: $id) {
        id
        identifier
        title
        description
        priority
        state { id name type }
        assignee { id name email }
        labels { nodes { id name } }
        createdAt
        updatedAt
      }
    }
  `;
  const data = await gql(query, { id: identifier });
  return data.issue;
}

// CLI handling
const [,, cmd, arg1] = process.argv;

if (!cmd || cmd === 'list') {
  listIssues(arg1 || 'active').then(issues => {
    console.log(`Found ${issues.length} ${arg1 || 'active'} issues for unb-wire-ngx:`);
    issues.forEach(i => {
      const labels = i.labels.nodes.map(l => l.name).join(', ');
      console.log(`• [${i.identifier}] (P${i.priority} | ${i.state.name}) ${i.title}${labels ? ' [' + labels + ']' : ''}`);
    });
  }).catch(console.error);
} else if (cmd === 'get') {
  if (!arg1) {
    console.error('Usage: node scripts/linear-client.cjs get <IDENTIFIER e.g. COS-74>');
    process.exit(1);
  }
  getIssue(arg1).then(issue => {
    console.log(`\n=== [${issue.identifier}] ${issue.title} (P${issue.priority} | ${issue.state.name}) ===`);
    console.log(`Assignee: ${issue.assignee ? issue.assignee.name : 'Unassigned'}`);
    console.log(`Labels: ${issue.labels.nodes.map(l => l.name).join(', ')}`);
    console.log(`\nDescription:\n${issue.description}\n`);
  }).catch(console.error);
} else {
  console.log('Usage: node scripts/linear-client.cjs [list|get] [filter|issueId]');
}

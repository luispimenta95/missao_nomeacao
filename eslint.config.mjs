// Fast lint tier for this JavaScript project. There is no TypeScript
// program, so there is no eslint.typed.config.mjs and no lint:types script.
//
// Adapted from the toolkit example:
//   - source is resources/js, scripts/, and vite.config.js (not src/)
//   - quality/no-direct-data-access is omitted: persistence is PHP
//     Eloquent, and no JavaScript module exports a database client
//   - import-x zones are omitted: the JS surface has no layer boundary
//   - quality/no-direct-console points at scripts/cli-log.mjs and is off only there
import js from "@eslint/js";
import { defineConfig, globalIgnores } from "eslint/config";
import globals from "globals";

import quality from "./eslint-rules/index.cjs";

export default defineConfig([
  {
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
      globals: {
        ...globals.node,
      },
    },
  },
  js.configs.recommended,
  {
    files: [
      "resources/js/**/*.{js,mjs,cjs}",
      "scripts/**/*.{js,mjs,cjs}",
      "vite.config.js",
    ],
    plugins: { quality },
    rules: {
      "no-empty": ["error", { allowEmptyCatch: true }],
      "no-var": "error",
      "prefer-const": "error",
      "no-unused-vars": [
        "error",
        { argsIgnorePattern: "^_", varsIgnorePattern: "^_" },
      ],
      complexity: ["error", 12],
      "max-depth": ["error", 4],
      "max-statements": ["error", 20],
      "max-params": ["error", 4],
      "max-lines-per-function": [
        "error",
        { max: 150, skipBlankLines: true, skipComments: true },
      ],
      "max-nested-callbacks": ["error", 3],
      "quality/max-lines": ["error", { max: 350 }],
      // baseline: 6
      "quality/no-direct-console": [
        "error",
        { logger: "scripts/cli-log.mjs" },
      ],
    },
  },
  {
    files: ["scripts/cli-log.mjs"],
    rules: {
      "quality/no-direct-console": "off",
    },
  },
  {
    // resources/js runs in the browser. The Puppeteer scripts are Node, but
    // the functions they pass to page.evaluate run in the Tutory admin page,
    // which exposes the DOM plus Chart.js, jsPDF and PDFWriter.
    files: ["resources/js/**/*.js", "scripts/**/*.{js,mjs,cjs}"],
    languageOptions: {
      globals: {
        ...globals.browser,
        Chart: "readonly",
        jsPDF: "readonly",
        PDFWriter: "readonly",
      },
    },
  },
  {
    files: [
      "**/*.test.{js,mjs,cjs}",
      "**/{__tests__,__mocks__,fixtures,mocks}/**/*.{js,mjs,cjs}",
    ],
    plugins: { quality },
    rules: {
      "quality/max-lines": ["warn", { max: 350, includeTests: true }],
    },
  },
  {
    files: ["**/*.test.{js,mjs,cjs}"],
    rules: {
      "max-statements": "off",
      "max-lines-per-function": "off",
      "max-nested-callbacks": "off",
    },
  },
  {
    files: ["eslint-rules/**/*.cjs"],
    languageOptions: {
      sourceType: "commonjs",
      globals: {
        ...globals.node,
        module: "readonly",
        require: "readonly",
      },
    },
  },
  globalIgnores([
    ".claude/**",
    ".github/agents/**",
    ".github/hooks/**",
    ".github/skills/**",
    "node_modules/**",
    "vendor/**",
    "dist/**",
    "build/**",
    "public/build/**",
    "storage/**",
    "bootstrap/cache/**",
    "coverage/**",
    "**/*.tsbuildinfo",
    "package-lock.json",
  ]),
]);

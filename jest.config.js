module.exports = {
  testEnvironment: 'jsdom',
  roots: ['<rootDir>/tests/js'],
  testMatch: ['**/*.test.ts'],
  transform: {
    '^.+\\.tsx?$': ['ts-jest', { tsconfig: 'tsconfig.json', diagnostics: false }]
  },
  moduleNameMapper: {
    '\\.(css|scss)$': '<rootDir>/tests/js/style-stub.js'
  }
};

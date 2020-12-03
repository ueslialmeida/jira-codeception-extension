jira-codeception-extension
=============================
This package provides an extension for Codeception to create Jira issues automatically when a test fails.

### Configuration Example

This extension creates a Jira issue after a test failure. To use this extension a valid Jira configuration is required.

Configuration 'codeception.yml' example:

    extensions:
      enabled:
        - Codeception\Extension\JiraExtension
      config:
        Codeception\Extension\JiraExtension:
          host: https://yourdomain.atlassian.net
          user: email@mail.com
          token: Tg7womaGGFpn9EC16qD3L7T6
          projectKey: JE
          issueType: Bug
          debugMode: false

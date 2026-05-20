# Idempotent seed script for local Redmine development.
# Run via: docker exec redmine bundle exec rails runner /seed/seed.rb

MODULES = %w[issue_tracking time_tracking wiki boards calendar gantt].freeze

def find_or_create_user(login:, firstname:, lastname:, mail:, password: 'password')
  user = User.find_by(login: login)
  return user if user

  user = User.new(
    login:     login,
    firstname: firstname,
    lastname:  lastname,
    mail:      mail,
    language:  'en',
    status:    User::STATUS_ACTIVE
  )
  user.password              = password
  user.password_confirmation = password
  user.save!
  puts "[seed] User created: #{login} (#{firstname} #{lastname})"
  user
end

def find_or_create_project(identifier:, name:, description:)
  project = Project.find_by(identifier: identifier)
  return project if project

  project = Project.create!(
    name:        name,
    identifier:  identifier,
    description: description,
    is_public:   true
  )
  project.trackers             = Tracker.all
  project.enabled_module_names = MODULES
  project.save!
  puts "[seed] Project created: ##{project.id} #{project.name}"
  project
end

def add_member(project, user, role_name: 'Developer')
  return if Member.exists?(project: project, user: user)

  role = Role.find_by(name: role_name) || Role.first
  Member.create!(project: project, user: user, roles: [role])
  puts "[seed]   #{user.login} added as #{role_name}"
end

def create_issue(project:, subject:, description:, author:, assigned_to:, tracker: nil, status: nil, priority: nil)
  return if Issue.exists?(project: project, subject: subject)

  issue = Issue.create!(
    project:     project,
    tracker:     tracker  || Tracker.first,
    subject:     subject,
    description: description,
    author:      author,
    assigned_to: assigned_to,
    status:      status   || IssueStatus.first,
    priority:    priority || IssuePriority.default
  )
  puts "[seed]   Issue ##{issue.id}: #{subject}"
  issue
end

def log_time(project:, issue:, user:, hours:, comment:, date: Date.today)
  return if TimeEntry.exists?(project: project, user: user, comments: comment)

  TimeEntry.create!(
    project:  project,
    issue:    issue,
    user:     user,
    hours:    hours,
    comments: comment,
    spent_on: date,
    activity: TimeEntryActivity.first
  )
  puts "[seed]   #{user.login} logged #{hours}h: #{comment}"
end

# ── API ───────────────────────────────────────────────────────────────────────
Setting[:rest_api_enabled] = '1'
puts "[seed] REST API enabled"

# ── Default data ──────────────────────────────────────────────────────────────
unless IssueStatus.any?
  Redmine::DefaultData::Loader.load('en')
  puts "[seed] Default data loaded"
else
  puts "[seed] Default data already present, skipping"
end

# ── Statuses & priorities ─────────────────────────────────────────────────────
status_new         = IssueStatus.find_by(name: 'New')         || IssueStatus.first
status_in_progress = IssueStatus.find_by(name: 'In Progress') || IssueStatus.first
status_resolved    = IssueStatus.find_by(name: 'Resolved')    || IssueStatus.first
priority_normal    = IssuePriority.find_by(name: 'Normal')    || IssuePriority.default
priority_high      = IssuePriority.find_by(name: 'High')      || IssuePriority.default

# ── Admin ─────────────────────────────────────────────────────────────────────
admin = User.find_by(login: 'admin')
unless admin.check_password?('admin')
  admin.password = admin.password_confirmation = 'admin'
  admin.save!
end
puts "[seed] Admin: admin / admin  |  API key: #{admin.api_key}"

# ── Test users ────────────────────────────────────────────────────────────────
puts "[seed] Users:"
alice = find_or_create_user(login: 'alice',   firstname: 'Alice',   lastname: 'Smith',   mail: 'alice@example.com')
bob   = find_or_create_user(login: 'bob',     firstname: 'Bob',     lastname: 'Jones',   mail: 'bob@example.com')
carol = find_or_create_user(login: 'carol',   firstname: 'Carol',   lastname: 'White',   mail: 'carol@example.com')
dave  = find_or_create_user(login: 'dave',    firstname: 'Dave',    lastname: 'Brown',   mail: 'dave@example.com')

# ── Project 1: MCP Test ───────────────────────────────────────────────────────
puts "[seed] Project: mcp-test"
mcp = find_or_create_project(
  identifier:  'mcp-test',
  name:        'MCP Test Project',
  description: 'Sandbox project for testing the Redmine MCP server'
)
add_member(mcp, admin, role_name: 'Manager')
add_member(mcp, alice, role_name: 'Developer')
add_member(mcp, bob,   role_name: 'Developer')

i1 = create_issue(project: mcp, subject: 'Setup CI pipeline',       description: 'Configure GitHub Actions', author: admin, assigned_to: alice, status: status_in_progress)
i2 = create_issue(project: mcp, subject: 'Write API documentation',  description: 'Document REST endpoints',  author: admin, assigned_to: bob,   status: status_new)
i3 = create_issue(project: mcp, subject: 'Fix login bug',            description: 'SSO fails on mobile',      author: alice, assigned_to: alice, status: status_new, priority: priority_high)
i4 = create_issue(project: mcp, subject: 'Add rate limiting',        description: 'Protect public endpoints', author: bob,   assigned_to: bob,   status: status_new)
i5 = create_issue(project: mcp, subject: 'Deploy to staging',        description: 'Ship v1.0 to staging env', author: admin, assigned_to: admin, status: status_resolved)

log_time(project: mcp, issue: i1, user: alice, hours: 3.0, comment: 'CI setup', date: Date.today - 2) if i1
log_time(project: mcp, issue: i1, user: alice, hours: 1.5, comment: 'Fix pipeline config', date: Date.today - 1) if i1
log_time(project: mcp, issue: i2, user: bob,   hours: 2.0, comment: 'Draft docs', date: Date.today - 1) if i2
log_time(project: mcp, issue: i5, user: admin, hours: 4.0, comment: 'Deploy & smoke tests', date: Date.today) if i5

# ── Project 2: Backend API ────────────────────────────────────────────────────
puts "[seed] Project: backend-api"
api = find_or_create_project(
  identifier:  'backend-api',
  name:        'Backend API',
  description: 'Core REST API service'
)
add_member(api, admin, role_name: 'Manager')
add_member(api, alice, role_name: 'Developer')
add_member(api, carol, role_name: 'Developer')
add_member(api, dave,  role_name: 'Reporter')

a1 = create_issue(project: api, subject: 'Implement JWT auth',       description: 'Replace session-based auth', author: admin, assigned_to: alice, status: status_in_progress, priority: priority_high)
a2 = create_issue(project: api, subject: 'Add pagination to /users', description: 'Cursor-based pagination',    author: alice, assigned_to: carol, status: status_new)
a3 = create_issue(project: api, subject: 'Performance: N+1 queries', description: 'Eager load associations',    author: carol, assigned_to: carol, status: status_new, priority: priority_high)
a4 = create_issue(project: api, subject: 'Write integration tests',  description: 'Cover all endpoints',        author: admin, assigned_to: alice, status: status_new)

log_time(project: api, issue: a1, user: alice, hours: 5.0, comment: 'JWT implementation', date: Date.today - 3) if a1
log_time(project: api, issue: a1, user: alice, hours: 2.5, comment: 'Tests & review fixes', date: Date.today - 1) if a1
log_time(project: api, issue: a3, user: carol, hours: 1.5, comment: 'Profiling', date: Date.today) if a3

# ── Project 3: Mobile App ─────────────────────────────────────────────────────
puts "[seed] Project: mobile-app"
mobile = find_or_create_project(
  identifier:  'mobile-app',
  name:        'Mobile App',
  description: 'iOS & Android client application'
)
add_member(mobile, admin, role_name: 'Manager')
add_member(mobile, bob,   role_name: 'Developer')
add_member(mobile, dave,  role_name: 'Developer')

m1 = create_issue(project: mobile, subject: 'Push notifications',      description: 'FCM/APNs integration',    author: admin, assigned_to: bob,  status: status_in_progress)
m2 = create_issue(project: mobile, subject: 'Offline mode',            description: 'Cache issues locally',    author: bob,   assigned_to: dave, status: status_new)
m3 = create_issue(project: mobile, subject: 'Dark mode support',       description: 'System theme detection',  author: dave,  assigned_to: dave, status: status_new)
m4 = create_issue(project: mobile, subject: 'App Store submission',    description: 'v1.0 release prep',       author: admin, assigned_to: bob,  status: status_new, priority: priority_high)

log_time(project: mobile, issue: m1, user: bob,  hours: 4.0, comment: 'FCM setup', date: Date.today - 4) if m1
log_time(project: mobile, issue: m1, user: bob,  hours: 3.0, comment: 'APNs integration', date: Date.today - 2) if m1
log_time(project: mobile, issue: m2, user: dave, hours: 2.0, comment: 'Local DB schema', date: Date.today - 1) if m2

# ── Summary ───────────────────────────────────────────────────────────────────
puts ""
puts "━" * 54
puts "  Redmine is ready at http://localhost:3000"
puts ""
puts "  admin  / admin     (admin)    API key: #{admin.api_key}"
puts "  alice  / password  (developer)"
puts "  bob    / password  (developer)"
puts "  carol  / password  (developer)"
puts "  dave   / password  (reporter)"
puts ""
puts "  Projects: mcp-test · backend-api · mobile-app"
puts "━" * 54

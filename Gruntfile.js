module.exports = function (grunt) {

	grunt.initConfig({
		pkg: grunt.file.readJSON("package.json"),

		clean: [ "dist/**" ],

		copy: {
			main: {
				files: [
					{
						src: [
							"./**",
							"!./node_modules/**",
							"!./Gruntfile.js",
							"!./package.json"
						],
						dest: "dist/<%= pkg.name %>/"
					}
				]
			}
		},

		compress: {
			options: {
				archive: "./dist/<%= pkg.name %>-<%= pkg.version %>.zip",
				mode: "zip"
			},
			all: {
				files: [{
					expand: true,
					cwd: "./dist/",
					src: [ "<%= pkg.name %>/**" ]
				}]
			}
		},

		jshint: {
			all: [
				"Gruntfile.js",
				"js/*.js",
				"!js/*.min.js"
			],
			options: {
				jshintrc: ".jshintrc",
				force: true
			}
		},

		uglify: {
			build: {
				options: {
					preserveComments: /^!/
				},
				files: [{
					expand: true,
					cwd: "js",
					dest: "js",
					src: [
						"*.js",
						"!*.min.js"
					],
					ext: ".min.js"
				}]
			}
		},

		makepot: {
			// @link https://github.com/cedaro/grunt-wp-i18n/blob/develop/docs/makepot.md
			target: {
				options: {
					type: "wp-plugin",
					domainPath: "languages/",
					exclude: [
						"dist/.*",
						"lib/.*",
						"node_modules/.*"
					],
					potHeaders: {
						poedit: true,
						"x-poedit-keywordslist": true,
						"x-poedit-sourcecharset": "UTF-8",
						"report-msgid-bugs-to":	"<%= pkg.author.email %>",
						"Last-Translator": "<%= pkg.author.name %> <<%= pkg.author.email %>>",
						"Language-Team": "<%= pkg.author.name %> <<%= pkg.author.email %>>"
					}
				}
			}
		}

	});

	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks("grunt-contrib-compress");
	grunt.loadNpmTasks("grunt-contrib-copy");
	grunt.loadNpmTasks("grunt-contrib-jshint");
	grunt.loadNpmTasks("grunt-contrib-uglify");
	grunt.loadNpmTasks("grunt-wp-i18n");

	grunt.registerTask("release", ["clean","copy","compress"]);

};

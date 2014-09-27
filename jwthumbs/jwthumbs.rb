gem 'rubygems'
gem 'jwthumbs'

filename = ARGV[0]

movie = Jwthumbs::Movie.new(filename + ".mp4")
movie.seconds_between = 5
movie.outdir = "../../uploads/vtt"

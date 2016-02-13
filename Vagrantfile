# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  # All Vagrant configuration is done here. The most common configuration
  # options are documented and commented below. For a complete reference,
  # please see the online documentation at vagrantup.com.

  # Every Vagrant virtual environment requires a box to build off of.
  # config.vm.box = "Official Ubuntu 12.04 current daily Cloud Image amd64"
  # config.vm.box_url = "http://cloud-images.ubuntu.com/vagrant/precise/current/precise-server-cloudimg-amd64-vagrant-disk1.box"

  #config.vm.box = "CentOS 6.7 x64 (Minimal, Puppet 4.2.3, Guest Additions 4.3.30)"
  #config.vm.box_url = "https://github.com/CommanderK5/packer-centos-template/releases/download/0.6.7/vagrant-centos-6.7.box"

  config.vm.box = "CentOS 6.7 x86_64 Minimal (VirtualBox Guest Additions 5.0.8, Chef: 12.5.1, Puppet 3.8.4)"
  config.vm.box_url = "https://developer.nrel.gov/downloads/vagrant-boxes/CentOS-6.7-x86_64-v20151108.box"

  # The url from where the 'config.vm.box' box will be fetched if it
  # doesn't already exist on the user's system.
  # config.vm.box_url = "http://domain.com/path/to/above.box"

  # Create a forwarded port mapping which allows access to a specific port
  # within the machine from a port on the host machine. In the example below,
  # accessing "localhost:8080" will access port 80 on the guest machine.
  # config.vm.network :forwarded_port, guest: 9430, host: 9430 # RPC Broker
  # config.vm.network :forwarded_port, guest: 8001, host: 8001 # VistALink
  # config.vm.network :forwarded_port, guest: 8080, host: 8080 # EWD.js
  # config.vm.network :forwarded_port, guest: 8000, host: 8000 # EWD.js Webservices
  # config.vm.network :forwarded_port, guest: 8081, host: 8081 # EWD VistA Term

  # cache specific   
  # config.vm.network :forwarded_port, guest: 57772, host: 57772 # System Management Portal
  # config.vm.network :forwarded_port, guest: 1972, host: 1972 # SuperServer    


  # Create a private network, which allows host-only access to the machine
  # using a specific IP.
  config.vm.network :private_network, ip: "192.168.33.11"

  # Create a public network, which generally matched to bridged network.
  # Bridged networks make the machine appear as another physical device on
  # your network.
  # config.vm.network :public_network

  # Share an additional folder to the guest VM. The first argument is
  # the path on the host to the actual folder. The second argument is
  # the path on the guest to mount the folder. And the optional third
  # argument is a set of non-required options.
  # config.vm.synced_folder "../", "/vagrant"

  # Define primary box name for all VM providers
  # More VMs could be added here to build a multi-box install and provision
  # accordingly
  config.vm.define "RAPTOR", primary: true do |vista|
  end

  # Amazon EC2 configuration
  config.vm.provider :aws do |aws, override|
    aws.access_key_id = ENV['AWS_ACCESS_KEY_ID']
    aws.secret_access_key = ENV['AWS_SECRET_ACCESS_KEY']
    aws.keypair_name = ENV['AWS_KEYPAIR_NAME']
    aws.ami = "ami-d9a98cb0"
    aws.instance_type = "t1.micro"
    override.vm.box = "dummy"
    override.ssh.username = "ec2-user"
    override.ssh.private_key_path = ENV['AWS_PRIVATE_KEY']
  end

  # Rackspace Cloud configuration
  config.vm.provider :rackspace do |rs, override|
    rs.username = ENV['RS_USERNAME']
    rs.api_key = ENV['RS_API_KEY']
    rs.flavor = /512MB/
    rs.image = /CentOS 6.7/
    rs.rackspace_region = :ord
    rs.public_key_path = ENV['RS_PUBLIC_KEY']
    override.ssh.private_key_path = ENV['RS_PRIVATE_KEY']
  end

  # Add 8 GB Drive to Virtualbox for /srv share used to 
  # store CACHE.DAT file that is 4.4GB 
  config.vm.provider :virtualbox do |vb|
    # Don't boot with headless mode
    # vb.gui = true
  #  file_to_disk = './tmp/large_disk.vdi'
  #  unless File.exists?(file_to_disk)
  #    vb.customize ['createhd', '--filename', file_to_disk, '--size', 8 * 1024]
  #  end
  #  vb.customize ['storageattach', :id, '--storagectl', 'IDE Controller', '--port', 1, '--device', 0, '--type', 'hdd', '--medium', file_to_disk]
    # Use VBoxManage to customize the VM. For example to change memory:
    vb.customize ["modifyvm", :id, "--memory", "2048"]
  end

  #
  # View the documentation for the provider you're using for more
  # information on available options.

  # Enable provisioning with Puppet stand alone.  Puppet manifests
  # are contained in a directory path relative to this Vagrantfile.
  # You will need to create the manifests directory and a manifest in
  # the file base.pp in the manifests_path directory.
  #
  # An example Puppet manifest to provision the message of the day:
  #
  # # group { "puppet":
  # #   ensure => "present",
  # # }
  # #
  # # File { owner => 0, group => 0, mode => 0644 }
  # #
  # # file { '/etc/motd':
  # #   content => "Welcome to your Vagrant-built virtual machine!
  # #               Managed by Puppet.\n"
  # # }
  #
  # config.vm.provision :puppet do |puppet|
  #   puppet.manifests_path = "manifests"
  #   puppet.manifest_file  = "init.pp"
  # end

  # Enable provisioning with chef solo, specifying a cookbooks path, roles
  # path, and data_bags path (all relative to this Vagrantfile), and adding
  # some recipes and/or roles.
  #
  # config.vm.provision :chef_solo do |chef|
  #   chef.cookbooks_path = "../my-recipes/cookbooks"
  #   chef.roles_path = "../my-recipes/roles"
  #   chef.data_bags_path = "../my-recipes/data_bags"
  #   chef.add_recipe "mysql"
  #   chef.add_role "web"
  #
  #   # You may also specify custom JSON attributes:
  #   chef.json = { :mysql_password => "foo" }
  # end

  # Enable provisioning with chef server, specifying the chef server URL,
  # and the path to the validation key (relative to this Vagrantfile).
  #
  # The Opscode Platform uses HTTPS. Substitute your organization for
  # ORGNAME in the URL and validation key.
  #
  # If you have your own Chef Server, use the appropriate URL, which may be
  # HTTP instead of HTTPS depending on your configuration. Also change the
  # validation key to validation.pem.
  #
  # config.vm.provision :chef_client do |chef|
  #   chef.chef_server_url = "https://api.opscode.com/organizations/ORGNAME"
  #   chef.validation_key_path = "ORGNAME-validator.pem"
  # end
  #
  # If you're using the Opscode platform, your validator client is
  # ORGNAME-validator, replacing ORGNAME with your organization name.
  #
  # If you have your own Chef Server, the default validation client name is
  # chef-validator, unless you changed the configuration.
  #
  #   chef.validation_client_name = "ORGNAME-validator"
  #
  
  config.vm.provision :shell do |s|
    s.path = "provision/setup.sh"
    s.args = "-e -i " + "#{ENV['instance']}"
  end
end